# Peloton
Peloton is a minimal web application and templating system.

It uses [Twig for templates](http://twig.sensiolabs.org/) and [Klein for
routing](https://github.com/klein/klein.php). What does that mean? Let's talk
requests.

## Requests

Let's imagine a web server hosting static HTML pages. Those are just plain text
files as I'm sure you know. A browser sends a request to the server. If the
server can find a file that matches the request it returns a status code of
`200 OK` and sends the contents of the file back. (If the server can't find it,
it sends a `404` status code back.)

We're abstracting that transaction a little. The server is configured to send
all requests for sites that use this application to `public/index.php`.

Typically `index.php` will be pretty minimal. That's because this app handles
requests, compiles templates and returns HTML. Klein handles the routing part.
Twig is the template engine.

Thus Peloton is a hybrid between CMS and static site. Templates are compiled on
the fly, but we have no database dependencies, making your app faster and more
secure. Peloton is designed for sites where the content is the center.

## Development
See [CONTRIBUTING](CONTRIBUTING.md)

## History
See [CHANGELOG](CHANGELOG.md)

## Credits
See [AUTHORS](AUTHORS.md)

## License
See [LICENSE](LICENSE)
