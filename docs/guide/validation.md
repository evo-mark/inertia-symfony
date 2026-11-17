# Validation errors

The bundle shares an `errors` prop on every request. It is an
[always prop](./props#always-props), so partial reloads include it too.

Errors are read from the session under the key `EvoMark\InertiaSymfonyBundle\Support\SessionKey::ERRORS`
(`inertia.errors`), and removed once they have been read, like a flash message. The
value is an array of error bags, each mapping a field to its message or messages:

```php
use EvoMark\InertiaSymfonyBundle\Support\SessionKey;

$request->getSession()->set(SessionKey::ERRORS, [
    'default' => [
        'email' => 'This value is not a valid email address.',
        'name' => ['Too short.', 'Must not be blank.'],
    ],
]);

return $inertia->back();
```

The client receives the `default` bag as `errors`, with the first message for each
field. Only a session the client already has is read, so pages don't start one.

## Error bags

Name a bag other than `default` to keep the errors of several forms apart:

```php
$session->set(SessionKey::ERRORS, ['login' => ['email' => 'Unknown user.']]);
```

Several bags are sent as an object keyed by bag name. When the client sends the
`X-Inertia-Error-Bag` header (Inertia's `errorBag` form option), the `default` bag is
returned under that name.

## With the Symfony Validator

Write a violation list into the session in the shape above, then redirect back:

```php
$violations = $validator->validate($dto);

if (\count($violations) > 0) {
    $errors = [];
    foreach ($violations as $violation) {
        $errors[$violation->getPropertyPath()][] = $violation->getMessage();
    }

    $request->getSession()->set(SessionKey::ERRORS, ['default' => $errors]);

    return $inertia->back();
}
```
