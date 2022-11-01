# Tailwindcss Un-abstracter
If you're like me and didn't read Tailwind's documentation about [premature abstractions](https://tailwindcss.com/docs/reusing-styles#avoiding-premature-abstraction) properly, your codebase is likely full of them. *sigh* 
This tool hopefully makes the process of un-abstracting your Tailwind CSS classes a little less painful and time-consuming.

## Features
- Is able to follow [SASS `@extend`](https://sass-lang.com/documentation/at-rules/extend) directives
- Interactive by default

## Usage
```shell
$ php app.php dfs:tailwindcss:unabstract -h

Description:
  Un-abstracts TailwindCSS classes given your HTML & SCSS files.

Usage:
  dfs:tailwind:unabstract <html-files> <tailwindcss-files> [<excluded-directories>...]

Arguments:
  html-files            Path to HTML files.
  tailwindcss-files     Path to Tailwindcss files.
  excluded-directories  Directories to exclude. [default: ["vendor","node_modules"]]

Options:
  -h, --help            Display help for the given command. When no command is given display help for the list command
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

## Sample output
```
I have found 379 abstractions in 115 files with 17 warnings (see above). Would you like to un-abstract? This will override your files! You will be asked each time. 
[y/n]: y

Replace abstraction 'heading--second' with 'text-xl font-heading mb-2' on line 5 in file src/Resources/views/storefront/slot/faq.html.twig?.
Context: '<p class="heading--second mb-0">{{- element.config.question.value -}}</p>'
```

## Contributing
This tool is hacked together rapidly, I'm open for pull requests. 

Some ideas:
- Ability to replace selectors only inside of `class` attributes in source files
- Auto replace custom css with [Tailwind CSS arbitrary values](https://tailwindcss.com/docs/adding-custom-styles#using-arbitrary-values)