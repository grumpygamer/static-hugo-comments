A single PHP file that allows comments on a static blog.

The blog must be served by a web server that understands PHP so isn't suitable for pure static delivery like a S3 bucket.

It's been tested on Apache, but other should work just as well on others.

It's been tested on [HUGO](https://gohugo.io/), but probably works on other static sites.

To install, copy `comments.php` to the `static` folder of your HUGO site.

Also add
```
AddHandler application/x-httpd-php .html
```
to an `.htaccess` file in the `static` directory.

In the `single.html` HUGO file add the follow after the `.Content` is output.
```
{{ if .Params.comments }}
  {{ safeHTML "<?php require $_SERVER['DOCUMENT_ROOT'] . '/comments.php'; ?>" }}
{{ end }}
```

The post must have a
```
comments: true
```
In the header for entries that you wish to show comments.

You may need to install the following PHP extensions.

Examples for Ubuntu

```
sudo apt install php-curl
sudo apt install php-sqlite3
sudo apt-get install php-mbstring
```

There are also several defines at the top of `comments.php` for customizations like captcha and notifying Discord of new comments.

Comments can be stored in a Sqlite database or on disk as flat files.



