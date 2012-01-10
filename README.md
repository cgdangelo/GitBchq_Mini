README
======

GitBchq
-------

An overengineered Git-Basecamp integration utility, adapted into an underengineered post-commit script thing.

You can use this to post commit logs and patches to todo lists and messages. It'll look something like this:

![Example](http://i.imgur.com/nERLb.png)

LICENSE
-------

[Modified BSD License](https://raw.github.com/cgdangelo/GitBchq_Mini/master/LICENSE)

INSTALLATION
------------

0. Just put it in the folder.

USE
---

1. Set the following git variables with `git config`:
    * **basecamp.projectid** Project id for this repository
    * **basecamp.apikey** Your BaseCamp API key
    * **basecamp.baseurl** e.g. https://company.basecamphq.com/
2. `./GitBchq_Mini.php` or `php GitBchq_Mini.php` to run the script.
3. Just follow the directions on the screen. Ctrl+C if you want to abort.

Note that the "additional information" prompt is a multiline prompt. Type whatever you want and then continue by entering `%EOF%` on a new line. This data will show up before the commit log in the BaseCamp comment.
