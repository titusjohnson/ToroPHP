# Toro

Toro is a PHP router for developing RESTful web applications and APIs. It is designed for minimalists who want to get work done.

This repository is an upgraded version of Toro, modified to support reverse linking. If your router is set up like so:

```php
<?php
Toro::serve(array(
    "/" => "HelloHandler",
    "/friends" => "FriendsHandler",
    "/lists/:alpha" => "ListsHandler",
));
```

You can produce reverse links like so, keeping all your routes in one centeral location instead of sprinkled through your template files.

```php
<a href="<?php echo Toro::path("friends")">Friends</a>
<a href="<?php echo Toro::path("lists", "param")">List</a>

produces:

<a href="/friends">Friends</a>
<a href="/lists/param">List</a>
```