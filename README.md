# sync_mailman_with_ad
Sync mailman groups with Active Directory group.

Script perform the adding of the AD group members (AD group name mailman) into mailman groups.  
Mailman groups should be created, names should be the same with AD.  

Specify your variables:  
```$ldapserver = '<your_server>';```  
```$ldapport = <your_port>;```  
```$ldapuser = '<your_user>';```   
```$ldappass = '<your_password>';```  
```$ou = 'dc=<your_domain>,dc=com';```  
