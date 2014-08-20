Seacloud
========
Seacloud is a Whatsapp bot that creates a chat channel through a Whatsapp contact. It is similar to having a Whatsapp group, except your details are never shared so people you are chatting with will never know your actual number unless you give it out.

In short it's kinda like IRC where you can join the channel and your actual number will never be known and you can take any identity you fancy; anonymously.

Running a Seacloud service
==========================
To run a Seacloud service you must have your contact number ready along with the password. Once you have that, create a `config.wcfg` file. Then start the Seacloud service by using `php Seacloud.php -c config.wcfg`. Details about making a `wcfg` file are below.

Do note that even though Seacloud uses encryption techniques to encrypt the persistence data, it is not required that you have mcrypt installed. However having mcrypt installed and enabled will certainly speed the encryption process up.

Seacloud configs
================
Seacloud requires a config to be made and referred to at runtime in order to both correctly separate authentication and make life easier when wanting to use different contacts for different services.

An example config:
```
number=123456789
pass=passwordhash
id=999
nick=Seacloud
owner=123456789
persistencekey=random
```
Where number is the number of the contact you will be using as the Seacloud interaction point and owner is your own personal number. Owner number is used to tell the service owner whether the service has been started correctly and to communicate any (future) errors.

As a developer you can add more entries and refer to them in code by using the provided `ConfigParser` instance in the `Seacloud` object.

---
FAQ
===

###What do I need to host a Seacloud channel service?
You first need a side contact, aka: a contact number that you own, but are not actively using. Then you need all the details for that contact such as its ID and password which you can get by using Miss Venom to sniff your details or Yowsup-cli to both register and get the details.

###Can I modify Seacloud and/or use it as a base for my own bot/service?
Sure you can, Seacloud was originally designed to be an anonymous chat channel, but also serves as an example code base for developers who would like to design/code a Whatsapp bot/service.

It is frowned upon though to modify the code base maliciously and still pass as a Seacloud service.

###Is there some sort of demo?
There is a development demo that's up sometimes (It will be up permanently once the project has reached a point in development where persistence is not an issue). You can demo it on this number: `+447745940363`
