Phlute (A PHP Code Generator)
=============================

Phlute is a single-file PHP script that generates a set of PHP class files based on an XML template.

It's basically just a run-of-the-mill code generator.  There's also not any documentation at the moment because it's primarily something that I was originally planning on being a small, quick script.

There is currently an example in place of documentation, phlute-input.xml.  You call this from the command line with `php phlute.php phlute-input.xml`, except you replace `phlute-input.xml` with whatever you want.  And from \*nix systems, you can make phlute.php executable so you can run it with `./phlute.php`.

(The modeline at the beginning for phml is a customized syntax highlighter for Vim, and that can be found in my vimsyntax repo.  It just highlights the CDATA parts as PHP.)

This helps map out larger projects, so I can think of them more conceptually instead of spending a lot of time typing code.

It's just for initial creation.  Not for modifying existing files.

It's written in PHP because I have a rule that I write code generators in the language that its generating code for.  I'm not sure where I got that rule, though.

THERE IS NO TEST.  Sorry.  I originally wrote this thinking it was going to be small enough that it wouldn't need one.  Even before the initial commit, though, it turned out to be huge.  I realize now that that was a mistake, so I'm going to correct that eventually.

Why is it called "Phlute"?  Because I'm terrible at naming things.
