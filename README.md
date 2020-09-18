# Phlute (A PHP Code Generator)

Phlute is a single-file PHP script that generates a set of PHP class files based on an XML template.

This helps map out larger projects, so I can think of them more conceptually instead of spending a lot of time typing code.  It's just for initial creation.  Not for modifying existing files.

*It requires that php-dom be installed!*  I assume this is the case for most people that have PHP.

It's basically just a run-of-the-mill code generator.  Documentation is primarily just reference material rather than detailed tutorials.  I assume that looking at phlute-input.xml should be enough to get you started, and the reference material below would be able to figure out any details.

There is an example in the form of phlute-input.xml.  You call this from the command line with `php phlute.php phlute-input.xml`, except you replace `phlute-input.xml` with whatever you want.  And from \*nix systems, you can make phlute.php executable so you can run it with `./phlute.php`.


## A few notes on the XML example:

The modeline at the beginning for phml is a customized syntax highlighter for Vim, and that can be found in my vimsyntax repo.  It just highlights the CDATA parts as PHP.

Due to the way that XML works with CDATA, it will probably crash with weird, confounding errors if the PHP contained within includes the character sequence `]]>`.  For the moment, there's not a whole lot that I can do about that, so the only thing I can think to say is not to do that, and adding a space (like `]] >`)should be enough to avoid making DOMDocument mistakenly think that's the end of the CDATA block.  (It doesn't come up that often in PHP anyway.)


## Some miscellaneous notes.

It's written in PHP because I have a rule that I write code generators in the language that its generating code for.  I'm not sure where I got that rule, though.

All testing is manual, but there are scripts that help.  There's not a whole lot that I can do to change that, from a practical perspective, due the the unusual nature of the project.

I do everything in Linux systems, so sorry if it doesn't work as well for Windows systems.

Why is it called "Phlute"?  Because I'm terrible at naming things.


# Reference

This is just references for elements as well as attributes defined in individual elements.  This is a *reference*, not a tutorial, so it's not going to be easy to follow on its own.  If you're wanting to learn how to use this, it would probably be easiest to use phlute-input.xml as an example to get started.  This reference material would be used then to help learn some more details.

### phlute (root element):

`default-output` defines the default output directory for class files.  These can be overwritten by the class definition (which is defined later).

### macros

The `macros` element group together definitions of individual `macro` elements.  A `macro` element will define text that can be re-used in the text content of other nodes, *excepting* nodes that use CDATA.

Macros can be called by using double bracket notation such as `{{macroname}}`.  Arguments can be passed into macros with spaces and are represented within the macro itself with `$1`, `$2`, `$3`, etc.

This applies only to node values, *not* node attributes.

For example, if I have a macro defined as `<macro name="mymac">This is a macro.</macro>`, then I can use it the following 
    
    <method return="void" name="mymethod">
        <doc>This is a {{mymac}} docblock.</doc>
    </method>

The actual docblock in the output will read "This is a This is a macro. docblock.", because we've inserted the macro in the middle.

If we wanted to use arguments, we can represent them with `$1 ... $n`, and we'd pass them into the macro using spaces.  If we want to pass an argument that includes spaces, we'd put the argument in double-quotes.

For example, if I have a macro defined as `<macro name="mymac">This is a macro.  This is "$1", the first argument, and this is "$2", the second argument.</macro>`, then I can use it in th the following.

    <method return="void" name="mymethod">
        <doc>This is a {{mymac potato "I like grilled asparagus."}} docblock.</doc>
    </method>

The actual docblock in the output will read 'This is a This is a macro.  This is "potato", the first argument, and this is "I like grilled asparagus.", the second argument. docblock.'

Putting a literal "$1" in the macro, a literal double-quote in an argument, and literal {{ or }} in the source is not currently supported, but probably will be eventually.

### class

The `class` element defines a class.  Its required attributes are `name` and `namespace`.  It's optional attributes are `extends`, `implements`, `author`, and `keywords`.

The `extends` attribute does not need to have a fully-qualified namespace, assuming that you properly reference the namespace.  What you put there is exactly what will be put in the class declaration.

The `keywords` attribute probably requires a little explanation.  As an attribute for a class, the only thing that it can do is define a class as being abstract, by using `keywords="abstract"`.

### superdocs

The `superdocs` element is a child of the `class` elements.  It is optional and can be omitted entirely.

This element groups together instances of `superdoc` elements.  A superdoc is basically a docblock that contains an extra note for the file/class, but not part of the actual class docblock.

You can have as many superdocs as you like, but in all likelihood there's not much reason to have more than one.  That's a matter of personal taste, though.

### doc, child of class element.

The doc element, the one that's the immediate child of the class element, defines the class docblocks.  It has no attributes.

### uses

This element groups together `use` elements that define classes to reference at the top of the file.  The only attribute is `value`, which is the fully-qualified namespace of the class being referenced.

There isn't technically a "supported" way to use aliases here, but the value isn't broken apart into arguments from spaces, so you can go ahead and just put it in the value attribute.

### traits

This element groups together `trait` elements that are declared at the top of a class.

### properties

The `properties` element is a parent element to both `property` and `constant` elements.

`property` elements will define properties *and* create corresponding public getters and setters.  Required attributes to the element are `type`, `name`, and `doc`.  Optional attributes are `default`, which defines the default value, and `getter` and `setter` attributes (the purpose of which is described below).

In lieu of a `default` attribute, can use a child element named `default`, then use CDATA to define a multiline value (like an array or just a long string).  If you do this, double-quotes won't be put in for strings automatically.  Can do the same with constants, but the name of the child element will be `value` instead of `default`.

The getter and setter can be suppressed by defining an optional `getter` or `setter` attribute and setting it to `0`, like `<property type="int" setter="0" name="nosetter" doc="This property has no setter function."/>

Custom getters and setters can be defined by child elements of the `property` element.  These getters and setters are defined exactly like other methods, but the xml element would be named `getter` or `setter`.

Type currently must be defined and that will be enforced in the resulting getter and setter, but a future update will allow "variant" where there is no enforced type.

An optional attribute for `property` elements is the `keywords` attribute.  A `property` element can have one keyword, and that is the `static` keyword.  If it is declared as static, its corresponding getters and setters will be static.

If a `property` element is an immediate child of the `properties` element, then it will have private visibility.  This is the default setting.  To have public or protected properties in the end result, you'll need to create a `public` or `protected` element as a child of the `properties` element and define a new `property` as a child element of that new `public`/`protected` element.  An example of this appears in phlute-input.xml.  (If you want consistency, you *can* create a `private` element, so all `property` elements are two generations removed from `properties`, including the private properties.)  (I *typically* never use anything other than private properties, so I usually don't bother with visibility at all and let the getters and setters handle it.  But there are still times when it's worthwhile to have a public or protected property.)

Constants will always appear before properties.  They naturally do not have getters and setters.  Their attributes are `name`, `type`, `doc`, and `value`, all of which are required (but may not actually cause an exception if missing).

Comments can be placed in between properties, and will appear in results as line comments.  Macros will apply to these comments.

### methods

The `methods` element has three child elements, all of which are optional: `public`, `protected`, and `private`.  The children of all three are the same, being `method` elements, but those groupings are defining the visibility, as you probably realize.

Comments can be placed in between methods, and will appear in results as line comments.  Macros will apply to these comments.

### method

The actual `method` elements have one required attribute, being `name`, and two optional attributes, being `return` and `keywords`.

`return` is the type for return value.  This handles both the definition in the docblock and the type enforcement.  If it's missing, then the docblock will say "variant" and no type will be enforced.  If it's "void", then no type will be enforced.  If it starts with `?`, then the docblock will remove it and append a `|null`.  If it contains `|`, then no return type will be enforced.

`keywords` here is similar to the `keywords` attributes for classes, but there's an additional option of `static` here.  So, you could have `keywords="abstract static"` and the method will be declared as both abstract and static.

The child elements of a method element are `doc`, `input`, and `content`.

`doc` is required for any method element, but it's the only required child element.

You can have as few or as many `input` elements as you like.  Its attributes are `type`, `name`, `desc`, and `passby`.  `type` and `name` are required, but `desc` and `passby` are optional.  `passby` can be `ref` or `val`.

The `content` element is optional and contains PHP within CDATA tags.  (This is similar to how an RSS feed would contain HTML within CDATA tags.)  (This is also why there's a modeline towards the beginning of the example file-- I modified the xml syntax highlighter for Vim to highlight the inside of CDATA as PHP.

`<?`, `<?php`, and `?>` at the beginning and end of the CDATA content is ignored.  Everything within this is dumped into the file contents in the output.  If there is no `content` tag, then the content of the function just contains a `// Todo.` comment.

Visibility for methods works exactly like properties-- It defaults to private, but can be made public or protected by making them children of a `public` or `protected` element.  (For methods, I generally like to use that for all of them.)

The `throws` child elements can be used to define `@throws` tags in the resulting docblock.  Multiple exceptions be defined here.  The full namespace of the exception will be used in the output, assuming that it's defined in the `uses` element.

### doc and desc elements and attributes.

A `doc` attribute is used to define the main description of a class, method, property, or constant's docblock.  A `desc` attribute is used to define a description of a particular item in the docblock.  In both cases, they end up in the docblock, but the `doc` attribute is the main description at the top of the docblock and the `desc` attribute is the description of a particular item at the bottom.

In both cases, child elements can be used instead of attributes.  (More-or-less the default/expected input for docs, in fact.)  If you use a child element, you can use macros.

### Other stuff.

Can delete files that would otherwise create by adding the `--delete` flag to the CLI.

Can overwrite existing files by adding the `--force` flag to the CLI.

Double line breaks for `<doc>` nodes result in double line breaks in docblock descriptions.  Single spaces are ignored and removed from result.  Can't really do single-spaced line breaks in docblocks.  For the moment, I'm not planning on changing this, but it's possible I might in the future.  I can definitely see a use case for things like examples, but I don't do that myself *that* often.
