## Service Registry

The 24ounce-core implements a lightweight and explicit Service Registry
to manage internal dependencies in a controlled and predictable way.

### Goals

- Avoid global state and hidden dependencies
- Prevent silent service overrides
- Ensure consistent lifecycle management
- Improve testability and maintainability

### Design Principles

- Services must be explicitly registered during the bootstrap phase
- Services are lazy-loaded and treated as singletons by default
- Resolving an unregistered service is considered a fatal configuration error
- The registry is not a general-purpose service locator

### Registration

Services are registered using factories:

```php
TwentyFourOunce_Registry::register('price_engine', function () {
    return new TwentyFourOunce_Price_Engine();
});
