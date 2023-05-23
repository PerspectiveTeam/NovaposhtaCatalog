## Perspective Novaposhta Catalog 

### Install this package from BitBucket
To install this package from BitBucket, use the following steps:

1. Go to Magento 2 root directory.
   1. Choose your M2 version(deprecated) 
      1.  For Magento 2 (up to 2.4.3) use following command enter the following commands:  
       ```
       composer config repositories.perspective_novaposhtacatalog vcs https://bitbucket.org/monteshot/novaposhta_catalog.git
       ```
  
       ```
       composer require perspective/module-novaposhtacatalog:"dev-prod-2.4.3" -vvv
       ```  
      2. For Magento 2 (from 2.4.4) use following command enter the following commands (deprecated soon, possible backports):  
       ```
       composer config repositories.perspective_novaposhtacatalog vcs https://bitbucket.org/monteshot/novaposhta_catalog.git
       ```  

       ```
       composer require perspective/module-novaposhtacatalog:"dev-prod-2.4.4" -vvv
       ```
      3. For Magento 2 (from 2.4.5) use following command enter the following commands:
       ```
       composer config repositories.perspective_novaposhtacatalog vcs https://bitbucket.org/monteshot/novaposhta_catalog.git
       ```  

       ```
       composer require perspective/module-novaposhtacatalog:"dev-prod-2.4.4" -vvv
       ```
2. Wait while all dependencies are update. 
3. Make an ordinary setup for the module
