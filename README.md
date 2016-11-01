twitter-api-php
===============

Even more simple PHP Wrapper for sending tweets to twitter via API v1.1 calls

The aim of this class is simple. You need to:

- Include the class in your PHP code
- [Create a twitter app on the twitter developer site](https://dev.twitter.com/apps/)
- Enable read/write access for your twitter app
- Grab your access tokens from the twitter developer site
- [Choose a twitter API URL to make the request to](https://dev.twitter.com/docs/api/1.1/)
- use the tweet-method to just tweet a text
- use the tweetImage-method to send an image with your text

You really can't get much simpler than that.

Installation
------------

Just include TwitterAPI.php in your application. 

How To Use
----------

#### Include the class file ####

```php
require_once('TwitterAPI.php');
```

#### Create a new object ####

```php
$twitter = new TwitterAPI(
    "YOUR_OAUTH_ACCESS_TOKEN",
    "YOUR_OAUTH_ACCESS_TOKEN_SECRET",
    "YOUR_CONSUMER_KEY",
    "YOUR_CONSUMER_SECRET"
);
```

#### Send a tweet ####

```php
$twitter->tweet("THIS IS YOUR TWEET");
```

#### Send a tweet with an image ####

```php
$twitter->tweetImage('THIS IS A TWEET WITH AN IMAGE', 'path/to/image.jpg');
```

That is it! Really simple, works great with the 1.1 API.  
Thanks to @lackovic10 and @rivers on SO and James Mallison for their awesome work!
