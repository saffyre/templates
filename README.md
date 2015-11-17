# Saffyre Templates

This is a simple templating engine for php. It uses standard php syntax and doesn't
require you to learn any new templating languages.

It *does* introduce a few template-specific features that make writing MVC views a little
less painful.

Please note that documentation here is not yet complete.

## Getting started

Use composer to install Saffyre Templates:

```bash
$ composer require saffyre/templates
```

In your php, specify the directory containing your template files:

```php
<?php

Saffyre\Template::$baseDir = "/path/to/templates";
```

### Create a template

Create a php file in the templates directory that you specified:

**example.php:**

```php
This is a template file. You can use <?=$this->regularPhpSyntax?> here.

<?php
    echo "You can also use regular php blocks.";
?>
```

### Use a template

When you want to use a template, create an instance of `Saffyre\Template` and `echo` it.
You can assign variables to it, which will be available inside the template.

```php
<?php

use Saffyre\Template;

$myTemplate = new Template('example.php');
$myTemplate->regularPhpSyntax = 'regular php syntax';

echo $myTemplate;
```

You can also pass variables into the `Template` constructor directly, using an array:

```php
<?php
$myTemplate = new Template('example.php', [ 'regularPhpSyntax' => 'regular php syntax' ]);
```

If you would rather retrieve the results of the template (instead of outputting it),
Simply "invoke" the template like a function:

```php
<?php
$results = $myTemplate();
```

Easy!
