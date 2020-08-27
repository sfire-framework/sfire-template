# Template Parser

- [Introduction](#introduction)
- [Requirements](#requirements)
- [Installation](#installation)
- [Setup](#setup)
    - [Namespace](#namespace)
    - [Instance](#instance)
    - [Configuration](#configuration)
- [Usage](#usage)
    - [Retrieving data from cache](#retrieving-data-from-cache)
    - [Storing data in cache](#storing-data-in-cache)
    - [Removing data from cache](#removing-data-from-cache)
    - [Renewing expiration of stored data](#renewing-expiration-of-stored-data)
    - [Check if cache exists](#check-if-cache-exists)
    - [Clear all cache data](#clear-all-cache-data)
- [Examples](#examples)
- [Notes](#notes)
    - [Chaining](#chaining)
    - [Data types](#data-types)



## Introduction

sFire Template is based on the VueJS template syntax. All sFire templates are valid HTML or XML that can be parsed by spec-compliant browsers and HTML/XML parsers. sFire Template helps you creates logic in HTML/XML files. Create `if` statements, `for` loops, include other HTML/XML files and much more. Translation is also within the package for easy creating multi-language sites/apps.



## Requirements

There are no requirements for this package.



## Installation

Install this package using [Composer](https://getcomposer.org/):
```shell script
composer require sfire-framework/sfire-template
```



## Setup

### Namespace
```php
use sFire\Template\Parser;
```

### Instance

```php
$cache = new Parser();
```



### Configuration

Below are the default values that this package uses.

#### Default settings
- Default template cache enabled is `true`
- Default amount of cache results is 5
- Default skip HTML comments is `true`



#### Overwriting settings

##### Enable/disable template cache

Rendered HTML templates are by default cached in a separate folder. The rendered template file will be saved as cache. This cache will be used for every other request to the template file for performance. sFire Template will recognize when the original template file has been modified, but if you want to disable caching you may do so by using the `enableCache()` method.

Syntax

```php
$parser -> enableCache(bool $enabledCache): void
```

Example:

```php
$parser -> enableCache(false);
```



##### Set amount of cache result



##### Skip HTML comments

By default, HTML comments are skipped and thus not returned in the render source. You can disable this feature for example debugging purposes, dependencies or other reasons by using the `setSkipComments()` method.

###### Syntax
```php
$cache -> setSkipComments(bool $skip): void
```
###### Example
```php
$cache -> setSkipComments(false);
```



## Usage

### Parser syntax

Below is the documentation of the parser itself. With the parser you can set new templates to render, add functions and methods, assign variables which you can use in the template and much more.



#### Setting the template cache directory

sFire Template needs a folder to store the template cache, even if cache is disabled. This is due to rendering PHP without external files is tricky. Therefore, you need to set a readable and writable folder first before using this package.

```php
$parser -> setCacheDir('/var/www/example/directory/cache/');
```



#### Setting a template file

To render a template, you first needs to set a template file. This can be done with the `setFile()` method. Template files must contain the extension `.html` or `.xml`.

```php
$parser -> setFile('/var/www/example/directory/templates/home.html');
```



#### Setting a root template directory

If you store the templates within one single directory, you can use the `setTemplateDir()` method to set the root directory for all templates. This method is optional and thus not required, but it makes life easier for not writing the base path to the template directory every time you render a template.

```php
$parser -> setTemplateDir('/var/www/example/directory/templates/');
```

After this, you can set a template file by calling the `setFile()` method without the base path.

```php
$parser -> setFile('home.html');
```



#### Parsing a template

```php
$parser = new Parser();
$parser -> setCacheDir('/var/www/cache/');
$parser -> setFile('/var/www/templates/home.html');

$output = $parser -> render();
echo $output;
```

You may also use `echo` to display the output directly without the need of calling the `render()` method:

```php
echo $parser;
```



#### Using variables

You can add variables within the template file by calling the `assign()` method. 

```php
$parser = new Parser();
$parser -> assign('foo', 'bar'); //Define one variable
$parser -> assign(['foo' => 'bar', 'baz' => 'quez']); //Define multiple variables
```

Now you can use the `foo` variable in you template file like:

```html
<!-- <div>bar</div> -->
<div>{{ $foo }}</div>
```

This way, the variable will be automatic be escaped with htmlentities.

You can overwrite this default behavior like:

```html
<div>{!! $foo !!}</div>
```

This way the variable won't be escaped, so be careful using this option.

You can also use the variables for other template functions like `if` statements:

```html
<div s-if="$foo === 'bar'">
    foo equals bar
</div>
```



#### Reference variables

For large datasets i.e. database results, instead of assigning variables (and making a copy of the data), you can use the `reference` method. This way the variables will have a reference (pointer) to the already existing dataset for memory improvement.

```php
$parser = new Parser();
$parser -> reference('foo', $results);
```

Within the template file:

```html
<ul>
	<li s-for="$item in $foo">{{ $item }}</li>
</ul>
```





#### Adding template functions

You can expand the built-in functionality by defining custom functions or class methods. 

By default, the result of an executed function is cached based on the given parameters. It will take 1000 times the execution of the function before the cache will be dropped and the function executed again. This limit can be changed by the optional `cache` parameter.

```php
$parser -> addFunction(string $functionName, callable $function, int $cache = 1000): void
```

If the function is called within a `for` loop with the same parameters, the function will only be executed once. You can set the cache limit to another value to match your needs. Set it to `0` to disable it.



##### Example

Adding a custom function.

```php
$parser -> addFunction('foo', function($a, $b) {
	return $a + $b;
});
```

Within the template file:

```html
<!-- Prints 7 -->
{{ foo(5, 2) }}
```



##### Example 2:

Limit the cache on the custom function.

```php
$parser -> addFunction('foo', function() {
	return 20;
}, 50);
```

```html
<ul>
	<li s-for="$index in 100">{{ foo() }}</li>
    <!-- <li>20</li> -->
</ul>
```

The above example will only execute the custom `foo` function twice because of the cache limit of `50` and the loop iterates `100` times.



##### Example 3:

All defined functions can be retrieved with the `getFunctions()` method.

```php
$functions = $parser -> getFunctions();
print_r($functions);
```



#### Using class methods as template functions

Instead of using functions, you can also define custom methods. 

##### Syntax:

```php
$parser -> addMethod(string $functionName, object $class, string $methodName, int $cache = 1000): void
```

The `addMethod()` method, behaves exactly the same as the `addFuncion()` method, but instead you pass a initialized class object and the name of the method that needs to be executed.

##### Example 1:

Adding a custom method.

```php
$parser -> addMethod('foo', new Foo(), 'bar');
```

```php
class Foo {
    
    public function bar($a, $b) {
        return $a + $b;
    }
}
```

##### Example 2:

All defined methods can be retrieved with the `getMethods()` method.

```php
$methods = $parser -> getMethods();
print_r($methods);
```



#### Using middleware

sFire Template lets you use middleware for setting functions, methods, assigning variables, etc. These will be available within all template files.

##### Syntax:

```php
$parser -> middleware(string ...$class): self
```

##### Example 1:

Setting one middleware class.

```php
$parser -> middleware('\App\Middleware\TemplateMiddleware');
$parser -> middleware(TemplateMiddleware::class);
```

##### Example 2:

Setting multiple middleware classes.

```php
$parser -> middleware(
    '\App\Middleware\LanguageMiddleware', 
    '\App\Middleware\RouterMiddleware'
);
```



The `constructor` of the middleware will be executed and populated with the instance of the parser.

```php
class TemplateMiddleware {

    public function __construct(Parser $parser) {
		
        //Assign a variable
        $parser -> assign('language', 'en');
        
        //Add a new function
        $parser -> addFunction('count', function(float $number1, float $number2): float {
           return $number1 + $number2; 
        });
        
        ...
    }
}
```





### Template syntax

Below is the documentation of the syntax of a single template file. All templates are valid HTML or XML. Within the template you can use `if` / `else` statements, loops, variables and much more.

Directives

Directives are special attributes with the `s-` prefix. Directive attribute values are expected to be a single PHP expression (with the exception of `s-for`, which will be discussed later).



#### Conditional Rendering

The directive `s-if` is used to conditionally render a block. The block will only be rendered if the directive’s expression returns a truthy value.

```html
<div s-if="1 == 1">Foo</div>
```

It is also possible to add an “else block” with `s-else`:

```html
<div s-if="1 == 1">Foo</div>
<div s-else>Baz</div>
```

The `s-elseif`, as the name suggests, serves as an “else if block” for `s-if`. It can also be chained multiple times:

```html
<div s-if="1 == 1">Foo</div>
<div s-elseif="1 == 2">Bar</div>
<div s-elseif="1 == 3">Quez</div>
<div s-else>Baz</div>
```



#### Attribute binding

```html
<!-- Bind post value on a input -->
<input type="text" name="username" s-bind:value="$_POST['username']">

<!-- Bind a variable title -->
<a href="/" s-bind:title="$title">Click</a>

<!-- Combine variable with static string -->
<img s-bind:src="$path . '/logo.png'">
```



#### Class and Style Bindings

A common need for data binding is manipulating an element's class list and its inline styles. Since they are both attributes, we can use `s-bind` to handle them: we only need to calculate a final string with our expressions. However, meddling with string concatenation is annoying and error-prone. For this reason, sFire Template provides special enhancements when `s-bind` is used with class and style. In addition to strings, the expressions can also evaluate to arrays.

##### Array Syntax
We can pass an object to s-bind:class to dynamically toggle classes:
```html
<div s-bind:class="['active' => true]"></div>
```
The above syntax means the presence of the `active` class will be determined by the truthiness of the data property which in this case is `true`. This can also be a variable, function or method.

You can have multiple classes toggled by having more fields in the array. In addition, the `s-bind:class` directive can also co-exist with the plain class attribute. So given the following template:
```html
<div
  class="static"
  s-bind:class="['active' => true, 'text-danger' => false]"
></div>
```
It will render:
```html
<div class="static active"></div>
```



#### For / Foreach loops

```html
<table>
    <tr s-for="$item in $items">
    	<td>{{ $item }}</td>
    </tr>
</table>

<table>
    <tr s-for="($item, $index) in $items">
        <td>{{ $index }}</td>
    	<td>{{ $item }}</td>
    </tr>
</table>

<table>
    <tr s-for="$item in 10">
    	<td>{{ $item }}</td>
    </tr>
</table>
```



s-tag

```html
<s-tag s-if="1 ==1">
Hello
</s-tag>
```

```xml
<catalog>
	<cd>
		<title>Empire Burlesque</title>
		<artist>Bob Dylan</artist>
	</cd>
</catalog>
```



render

```php
$output = $parser -> render();
echo $output;
```



### Examples

#### Example -  Creating own custom XSS token preventing CSRF attacks

```php
$parser = new Parser();
$parser -> setCacheDir('/var/www/cache/');
$parser -> setFile('/var/www/templates/home.html');
$parser -> middleware(XssToken::class);

echo $parser;
```

XssToken class:

```php
class XssToken {
	
   const TOKEN_AMOUNT = 10;
    
    public function __construct(Parser $parser) {

        $parser -> addFunction('token', function(): string {

            $tokens  = [];
            $token   = uniqid();

            if(false === isset($_SESSION['tokens'])) {
                $_SESSION['tokens'] = [];
            }
            else {

                $tokens = $_SESSION['tokens'];

                if(count($tokens) > self::TOKEN_AMOUNT) {
                    $tokens = array_slice($tokens, -1 * (self::TOKEN_AMOUNT), self::TOKEN_AMOUNT);
                }
            }

            $tokens[] = $token;
            $_SESSION['tokens'] = $tokens;

            return $token;
        });
    }
}
```

Template file "home.html"

```html
<form action="" method="post">
    <input type="text" name="username">
    <input type="password" name="password">
    <input type="hidden" name="token" s-bind:value="token()">
    <input type="submit" value="Send">
</form>
```

