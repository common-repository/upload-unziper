=== Upload Unzipper ===
Contributors: ulfben
Donate link: http://www.amazon.com/gp/registry/wishlist/2QB6SQ5XX2U0N/105-3209188-5640446?reveal=unpurchased&filter=all&sort=priority&layout=standard&x=21&y=17
Tags: upload, zip, unzip, batch 
Requires at least: 2.2
Tested up to: 2.2.3
Stable tag: trunk

Extracts uploaded zip archives and associates all files with the current post.

== Description ==

Upload Unziper let's you upload zip-archives and have them extracted, each file properly attached to the current post. 

It's built upon and meant to replace James Revillini's now-broken [just-unzip](http://james.revillini.com/projects/just-unzip/), and expands on the original plugin in the following ways:

* runs all files through WP's sanitize filter to ensure valid filenames
* does not attach duplicates
* does not replace files with the same name
* correctly deals with nestled directories 
* uses the latest WP core functionality and the latest PclZip version
* and - perhaps most importantly - it *works*! ;)

In short - it's a nice way to do batch uploading. I highly recommend you combine it with an [inline image viewer](http://wordpress.org/extend/plugins/mini-slides/) and a plugin to better [organize your uploads](http://wordpress.org/extend/plugins/custom-upload-dir/).

== Installation ==

1. Extract the `wp-upload-unzipper` folder and transfer it to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Upload some zips. :)

== TODO (help needed) ==
This plugin could do with a few options. As it is, it'll always unzip archives and then delete them - which might not always be desired.

There are options for this already used in the plugin so it shouldn't be a problem, but I just loath front-end development. Placing a few tickboxes in the 'upload'-iframe would do the trick. If you've got a few minutes to throw something together, please email me or post a comment to this plugin.

Options I'd like exposed:

* delete zip when done (default: true)
* unzip and attach (default: true)
* attach zip to post (default: false)

== Frequently Asked Questions ==
= Can I have the ZIP-file attached to the post too? =
Yes. But you'll have to alter the source. Open `wp-upload-unzipper.php` and set `$addZipToPost` to `true`.

= Can I keep the ZIP-file on the server? = 
Open `wp-upload-unzipper.php` and set `$deleteZipWhenDone` to `false`.

= Can I disable the automatic extraction of archives? = 
Disable the plugin. :) 

If you're thinking of contributing a front-end for this plugin, you'd want to check out `$unzipArchives` in `wp-upload-unzipper.php`.

== Other Notes ==
Copyright (C) 2007 Ulf Benjaminsson (ulf at ulfben dot com).

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA