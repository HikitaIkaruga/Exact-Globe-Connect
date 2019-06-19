# Exact-Globe-Connect
PHP based class to connect to the API of Exact Globe

## Installation
This is a class to connect to the less popular Exact Globe (Not Online, they are not cross compatible). A connection to the server hosting your instance of Exact Globe is needed with the port 8020 (This is the default port) open. In Exact Globe create an user with full access and use this user in the userName / Password fields. No further changes are required in the settings.

## Known issues
There is a pretty insidious memory leak somewhere on the Exact Globe side. Should you be recieving errors involvings creating instances, than a full reboot is required.
