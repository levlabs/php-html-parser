# Event-driven HTML Parser written in PHP

PHP HTML Parser is a straightforward and flexible event-driven tool designed for lexing and parsing HTML markup. It offers an efficient mechanism for extracting data from HTML markup, providing an alternative approach to Document Object Model (DOM) implementations. While DOM typically processes the entire document, constructing a complete abstract syntax tree, this parser works sequentially, handling each markup piece individually. It generates parsing events as it navigates through the input in a single pass, ensuring efficient processing.

- **Written in PHP**: The parser is entirely implemented in PHP, making it easy to integrate and use within PHP projects.
- **Event-driven**: The parser is implemented as an generator function. It only needs to report each parsing event as it happens, and normally discards almost all of that information once reported
- **No External Dependencies**: Keeping things simple. That's why `php-html-parser` has zero external dependencies
