Phlute (A PHP Code Generator)
=============================

Phlute is a single-file PHP script that generates a set of PHP class files based on an XML template.

This helps map out larger projects, so I can think of them more conceptually instead of spending a lot of time typing code.  It's just for initial creation.  Not for modifying existing files.

It requires that php-dom be installed!  I assume this is the case for most people that have PHP.

It's basically just a run-of-the-mill code generator.  There's also not any documentation at the moment because it's primarily something that I was originally planning on being a small, quick script.

There is currently an example in place of documentation, phlute-input.xml.  You call this from the command line with `php phlute.php phlute-input.xml`, except you replace `phlute-input.xml` with whatever you want.  And from \*nix systems, you can make phlute.php executable so you can run it with `./phlute.php`.


A few notes on the XML example:

The modeline at the beginning for phml is a customized syntax highlighter for Vim, and that can be found in my vimsyntax repo.  It just highlights the CDATA parts as PHP.

Due to the way that XML works with CDATA, it will probably crash with weird, confounding errors if the PHP contained within includes the character sequence ']]>'.  For the moment, there's not a whole lot that I can do about that, so the only thing I can think to say is not to do that, and adding a space (like ]] >)should be enough to avoid making DOMDocument mistakenly think that's the end of the CDATA block.  (It doesn't come up that often in PHP anyway.)  (Also, I do have a plan to get around that soon.)


It's written in PHP because I have a rule that I write code generators in the language that its generating code for.  I'm not sure where I got that rule, though.

All testing is manual, but there are scripts that help.  There's not a whole lot that I can do to change that, from a practical perspective, due the the unusual nature of the project.

I do everything in Linux systems, so sorry if it doesn't work as well for Windows systems.

Why is it called "Phlute"?  Because I'm terrible at naming things.
