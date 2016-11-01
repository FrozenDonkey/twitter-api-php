<?php
ini_set('display_errors', 1);
require_once('TwitterAP.php');

$twitter = new TwitterAPI(
    "YOUR_OAUTH_ACCESS_TOKEN",
    "YOUR_OAUTH_ACCESS_TOKEN_SECRET",
    "YOUR_CONSUMER_KEY",
    "YOUR_CONSUMER_SECRET"
);

$res = $twitter->tweet("THIS IS YOUR TWEET");
echo ($res) ? 'DONE! :)' : 'ERROR! :(';

$res = $twitter->tweetImage('THIS IS A TWEET WITH AN IMAGE', 'path/to/image.jpg');
echo ($res) ? 'DONE! :)' : 'ERROR! :(';