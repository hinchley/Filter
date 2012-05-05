Filter
======
This elegant but powerful filter system for PHP offers the following benefits:

 * Integrates into existing code with minimal effort.
 * You can assign multiple filters to the same method.
 * Rather than requiring separate "before" and "after" clauses, filters "wrap around" methods (i.e. you can execute code before, after, or *both before and after* a method with a single filter).
 * New filters can be assigned to a method, even after the method has been called.
 * You can filter methods within objects and static classes; the code is identical.
 * Filters are chained together, allowing you to pass data, including the parameters of the filtered method, to all assigned filters.
 * Because Filter is implemented as a trait, it doesn't force you to alter your class inheritance hierarchy.
 * The code footprint is *tiny*.

Usage
-----
### Requirements
Filter requires PHP 5.4.

### Overview
To use Filter, you must:

 1. Include the Filter source file and use the Filter trait within a class.
 3. (Optional) Hook one or more methods within the class.
 4. Apply one or more filters to a class method.

#### 1. Including the Filter Trait
Traits are a language construct introduced in PHP 5.4 that allow you to insert a block of code into a class. Assuming ``filter.php`` is within the same folder as the class you wish to filter, you can include it with the ``require`` statement, and reference it via the ``use`` statement as shown:

    // Include the Filter source file.
    require 'filter.php';

    class Example {
      // Use the Filter trait.
      use Filter;

      // Class methods go here.
    }

#### 2. Hook a Method
In most cases, to filter a method, you will first need to prepare the method to be filtered (a process referred to as "hooking").

Let's assume you have a simple class:

    class Example {
      public static function speak($word) {
        return $word;
      }
    }

To "hook" the ``speak`` method you must wrap the method body within a closure using the static ``hook`` method. The ``hook`` method takes one optional and two mandatory parameters. The first parameter is an enumerated array of method arguments. In the example shown below, we use the ``compact`` method to add $word into the $args array. The second parameter is the closure. It also receives the enumerated arguments array as a parameter; this allows you to access the method arguments within the closure.

    class Example {
      use Filter;

      public static function speak($word) {
        // Arguments must be passed via an enumerated array.
        $args = compact('word');

        return static::hook($args, function($args) {
          // Access method arguments within the closure.
          return $args['word'];
        });
      }
    }

You've now successfully "hooked" the ``speak`` method so that it can be filtered.

The code becomes even simpler when the filtered method doesn't take any parameters; just pass an empty array as the first argument of ``hook``:

    class Example {
      use Filter;
      
      public static function hello() {
        return static::hook([], function($args) {
          return 'hello';
        });
      }
    }

The Filter trait uses a backtrace to work out the name of the hooked method. This reduces the code you need to write, but it does carry a small overhead, as generating a backtrace is a relatively slow task. If performance is your number one priority, you can avoid this overhead by explicitly calling ``hook`` with an optional third parameter: the name of the hooked method. This is most easily achieved using the \__FUNCTION__ magic constant:

    class Example {
      use Filter;
      
      // Avoid a backtrace by explicitly passing the method name.
      public static function hello() {
        return static::hook([], function($args) {
          return 'hello';
        }, __FUNCTION__);
      }
    }

#### 3. Assigning Filters
Assigning a filter to a method is ridiculously easy. You simply call the ``filter`` method with two parameters: the name of the method you wish to filter, and the anonymous function that implements the filter logic.

The following code defines a filter for the ``speak`` method of the ``Example`` class. The filter wraps the output in double quotation marks.

    Example::filter('speak', function($args) {
      // Code here executes before the 'speak' method.
      $result = '"';
      
      // Get the output of the next filter in the chain.
      $result .= static::next($args);
      
      // Code here executes after the 'speak' method.
      return $result.'"';
    });

Which can also be written in more compact form as follows:

    Example::filter('speak', function($args) {
      return '"'.static::next($args).'"';
    });

The output *before* the filter was applied:

    // Prints: Hi
    echo Example::speak('Hi');

The output *after* the filter was applied:

    // Prints: "Hi"
    echo Example::speak('Hi');

You may be wondering about the ``next`` method. This is a compulsory inclusion in every filter. It executes the next filter within the filter chain, eventually terminating with the filtered method.

Let's apply another filter, this time adding a trailing exclamation mark:

    Example::filter('speak', function($args) {
      // Add an exclamation mark to the result of the filter chain.
      return static::next($args).'!';
    });

Now when the ``speak`` method is called, the filter that assigns double quotes will "wrap around" the filter that applies the exclamation mark:

    // Prints: "Hi!"
    echo Example::speak('Hi');

As you might expect, accessing arguments within your filter is dead simple; just extract them from the $args array. For example, the following filter aborts the filter chain if the input to the ``speak`` method contains the word "pig":

    Example::filter('speak', function($args) {
      // Abort if 'speak' was called with an argument of 'pig'.
      if ($args['word'] == 'pig') return;

      // Otherwise, continue with the filter chain.
      return static::next($args).'!';
    });

#### Objects vs Static Classes
So far the examples have all focused on static methods. The good news is that Filter also works with objects in **exactly the same way**. Check this out:

    class Example {
      use Filter;

      public function speak($word) {
        $args = compact('word');
        return static::hook($args, function($args) {
          return $args['word'];
        });
      }
    }

    Example::filter('speak', function($args) {
      return static::next($args).'!';
    });

    $example = new Example();

    // Prints: Hi!
    echo $example->speak('Hi');

#### Filtering without a Hook
Although the process of hooking a method is straight forward, it still requires you to alter the implementation of your filtered methods. However, if you want to totally minimise the changes to your existing code base, Filter has you covered.

Let's say you've got the following code:

    class Example {
      use Filter;

      public function speak($word) {
        return $word;
      }
    }

And the following filter:

    Example::filter('speak', function($args) {
      return static::next($args).'!';
    });

Without the ``hook`` method, the filter won't execute when you call ``speak``. However, it will execute if you invoke an instance of the ``Example`` class, providing the name of the target method as the first parameter, and an array of method arguments as the second parameter:

    // Create an instance of the Example class.
    $example = new Example();

    // Execute the 'speak' method with an input of 'Hi'.
    // Result: Hi!
    echo $example('speak', ['Hi']);

Sweet, hey?

#### Addendum
This documentation has used the terms 'argument' and 'parameter' interchangeably to avoid sentences that repeatedly used the same word. I trust you [know the difference](http://en.wikipedia.org/wiki/Parameter_%28computer_programming%29#Parameters_and_arguments "Parameters and Arguments at Wikipedia") and will forgive my loose language.