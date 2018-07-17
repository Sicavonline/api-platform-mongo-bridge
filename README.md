# API Platform Mongo Bridge

Add the mongodb functionnality to api platform with all the extension enable

Install
-------
First you need to install MongoDB PHP Driver, Api platform

Run this command to configure your environnement informations : 

```
composer config "platform.ext-mongo" "1.6.16" # alcaeus dependancies
composer config "platform.ext-mongodb" "1.2.0" # alcaeus dependancies
```

After run the command : 

```
composer require sol/api-platform-mongo-bridge
```

We have no recipe for now so copy the file inside the vendor named "mongo_bridge_service.yml" into you config folder and here you are.
