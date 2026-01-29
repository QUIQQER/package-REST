![QUIQQER REST](bin/images/Readme.jpg)

QUIQQER REST
========

With QUIQQER REST you are able to implement a REST Server in QUIQQER and create
packages that bring their own REST endpoints. 

Package Name:

    quiqqer/rest


Features
--------
* Build packages that provider their own custom REST API endpoints
* Based on Slim (https://www.slimframework.com/)

Usage
---
See our [Wiki](https://dev.quiqqer.com/quiqqer/package-rest/wikis/dev/package) for help (currently available in German only)

Installation
------------

The Package Name is: `quiqqer/rest`

**Note on Authentication:**  
To prevent unauthorized access to REST endpoints, this package requires an implementation of `quiqqer/rest-authentication-implementation`. 

You must install a package which provides `quiqqer/rest-authentication-implementation` alongside this package for it to work.

Composer will give you an up-to-date list of packages that provide such an implementation, when just requiring `quiqqer/rest`:

```
❯ composer require --dry-run quiqqer/rest 

[...]

Your requirements could not be resolved to an installable set of packages.

  Problem 1
    - Root composer.json requires quiqqer/rest -> satisfiable by quiqqer/rest[3.0.0].
    - quiqqer/rest 3.0.0 requires quiqqer/rest-authentication-implementation ^1 -> could not be found in any version, but the following packages provide it:
      - quiqqer/oauth-server Manage and restrict access to REST API endpoints via OAuth authentication
      - quiqqer/rest-dummy-authentication A dummy authentication provider for QUIQQER REST endpoints that permits all requests without validat
      - quiqqer/rest-auth-token Token authentication for quiqqer/rest
      Consider requiring one of these to satisfy the quiqqer/rest-authentication-implementation requirement.

[...]
```

Example:  
To use OAuth as an authentication implementation for `quiqqer/rest`, you would require it like this (in one command):

```
composer require quiqqer/rest quiqqer/oauth-server
```


Contribute
----------
- Project: https://dev.quiqqer.com/quiqqer/rest
- Issue Tracker: https://dev.quiqqer.com/quiqqer/rest/issues
- Source Code: https://dev.quiqqer.com/quiqqer/rest/tree/master

Support
-------
If you found any errors or have wishes or suggestions for improvement,
please contact us by email at support@pcsg.de.

We will transfer your message to the responsible developers.

License
-------
GPL-3.0+