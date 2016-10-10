<?php
set_time_limit(30);
error_reporting(E_ALL);
ini_set('error_reporting', E_ALL);
ini_set('display_errors',1);

# config
$ldapserver = '<your_server>';
$ldapport = <your_port>;
$ldapuser = '<your_user>';
$ldappass = '<your_password>';
$ou = 'dc=<your_domain>,dc=com';

$ldapconn = ldap_connect($ldapserver, $ldapport) or die("Could not connect to LDAP server.");
$ldapbind = ldap_bind($ldapconn, $ldapuser, $ldappass) or die ("Error trying to bind: ".ldap_error($ldapconn));

# get blocked users from AD
$ldaptree = "ou=Employees,$ou";
$result = ldap_search($ldapconn,$ldaptree, "(&(objectClass=user)(userAccountControl:1.2.840.113556.1.4.803:=2))") or die ("Error in search query: ".ldap_error($ldapconn));
$data = ldap_get_entries($ldapconn, $result);
$blocked_users = array();
for ($i=0; $i<$data['count']; $i++) {
  $dn = $data[$i]['distinguishedname'][0];
  $blocked_users[$dn] = 1;
}

# get all users from AD
$ldaptree = "ou=Employees,$ou";
$result = ldap_search($ldapconn,$ldaptree, "(cn=*)") or die ("Error in search query: ".ldap_error($ldapconn));
$data = ldap_get_entries($ldapconn, $result);
$all_users = array();
for ($i=0; $i<$data['count']; $i++) {
  $dn = $data[$i]['distinguishedname'][0];
  $mail = @$data[$i]['mail'][0];
  if(!empty($mail)){
    $all_users[$dn] = $mail;
  }
}

# get mailman groups and their members from AD
$ldaptree = "ou=mailman,$ou";
$result = ldap_search($ldapconn,$ldaptree, "(cn=*)") or die ("Error in search query: ".ldap_error($ldapconn));
$data = ldap_get_entries($ldapconn, $result);
$lists = $lists_users = array();
for ($i=0; $i<$data['count']; $i++) {
  $mmlist = ucfirst($data[$i]['cn'][0]);
  echo "List: $mmlist\n";

  $cmd = "/usr/lib/mailman/bin/list_lists -b | grep -x -c -i $mmlist";
  $cmd = exec($cmd);
  # list not found, skip it
  if($cmd == 0){ continue; }

  foreach(array_slice($data[$i]['member'],1) as $k => $v){
    # email not found, skip it
    if(!isset($all_users[$v])){ continue; }

    $mail = $all_users[$v];

    $cl = "/usr/lib/mailman/bin/list_members $mmlist | grep -i -c -x $mail";
    $cmd = exec($cl);
    if($cmd > 1){
      # duplicate, remove one address
      $cd = "echo '$mail' | /usr/lib/mailman/bin/remove_members -n -f - $mmlist";
      exec($cd);
    }
    else if($cmd == 0){
      # add to list
      $ca = "echo '$mail' | /usr/lib/mailman/bin/add_members -w n -r - $mmlist";
      exec($ca);
    }

    if(!empty($blocked_users[$v])){
      $mail_blocked = $all_users[$v];
      # remove these users from mailman
      $cl = "/usr/lib/mailman/bin/list_members $mmlist | grep -i -c -x $mail_blocked";
      $cmd = exec($cl);
      if($cmd == 1){
        $cd = "echo '$mail_blocked' | /usr/lib/mailman/bin/remove_members -n -f - $mmlist";
        exec($cd);
      }
    }

  }
}

ldap_close($ldapconn);
