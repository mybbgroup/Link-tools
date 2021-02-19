## Custom Link Previewers

Use this directory to store your custom Link Previewers. Link Previewers in this directory take precedence over those in the `../link-previewers-dist` directory, meaning that if at least one Link Previewer in this directory returns true for `supports_link()`, the Link Previewers in the `../link-previewers-dist` directory are not even checked.

## Conventions

Link Previewers should be named `LinkPreviewerYourDescription.php` where you should replace `YourDescription` with a brief indication of the type of links it supports. Within the file, a class named `LinkPreviewerYourDescription` should be defined, which extends the base `LinkPreviewer` class (in `../LinkPreviewerBase.php`).

Other than that, please consult the Previewers in the `../link-previewers-dist` directory to understand how to write your custom Previewer.

## Under development; subject to change

Note that the Link Previewer framework is still under development, and potentially subject to change.