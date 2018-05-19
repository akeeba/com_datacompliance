<?php
// Sorry for this line, it's to keep broken security scanners happy. Skip it and read the rest of the file.
defined('_JEXEC') or die; die;

/**
 * This directory is required for the correct operation of the Joomla! back-end.
 *
 * Joomla! menu manager will look for XML files describing front-end views in the "views" directory, even though we
 * actually use the "View" directory (singular, first letter uppercase) to hold our files. This is a limitation of
 * Joomla!, unfortunately. The not-so-elegant solution is to have this directory just for these XML files. Without
 * this directory the menu manager will not allow you to create menu items for Akeeba Data Compliance.
 */