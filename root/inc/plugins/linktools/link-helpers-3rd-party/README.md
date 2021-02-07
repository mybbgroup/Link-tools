## Custom Link Helpers

Use this directory to store your custom Link Helpers. Link Helpers in this directory take precedence over those in the `../link-helpers-dist` directory, meaning that if at least one Link Helper in this directory returns true for `supports_link()`, the Link Helpers in the `../link-helpers-dist` directory are not even checked.

## Conventions

Link Helpers should be named `LinkHelperYourDescription.php` where you should replace `YourDescription` with a brief indication of the type of links it supports. Within the file, a class named `LinkHelperYourDescription` should be defined, which extends the base `LinkHelper` class (in `../LinkHelperBase.php`).

Other than that, please consult the Helpers in the `../link-helpers-dist` directory to understand how to write your custom Helper.

## Under development; subject to change

Note that the Link Helper framework is still under development and subject to (potentially major) change, including the very name "Link Helper", which might be changed to "Link Previewer".