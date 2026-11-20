# Validation

Inertia forms expect the server to answer an invalid submission by redirecting back
to the form, with the errors in the `errors` prop of the next page. `useForm` then
shows them as `form.errors`.

You don't have to build that response. Validate the way you would in any Symfony
app, with [constraints](https://symfony.com/doc/current/validation.html) and
[`#[MapRequestPayload]`](https://symfony.com/doc/current/controller.html#mapping-request-payload),
and the bundle turns Symfony's validation failure into the redirect Inertia expects.

## Requirements

`#[MapRequestPayload]` needs the Validator and the Serializer:

```sh
composer require validator serializer
```

## Validating a form

Describe the submission as a class, with constraints on its properties:

```php
// src/Dto/ContactMessage.php
namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContactMessage
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name = '',

        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 10, max: 5000)]
        public string $message = '',
    ) {
    }
}
```

Defaulting each field to `''` means a field missing from the request fails
`NotBlank` with a readable message, rather than a type error.

Map the request onto it in the controller. The action only runs for a valid
submission:

```php
use App\Dto\ContactMessage;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

#[Route('/contact', name: 'app_contact_submit', methods: ['POST'])]
public function submit(#[MapRequestPayload] ContactMessage $message, InertiaInterface $inertia): RedirectResponse
{
    // $message is valid here.

    $inertia->flash('success', 'Thanks, we will be in touch.');

    return $this->redirectToRoute('app_contact');
}
```

On the frontend, submit with `useForm` and show each field's error:

```vue
<script setup>
import { useForm } from "@inertiajs/vue3";

const form = useForm({ name: "", email: "", message: "" });
</script>

<template>
    <form @submit.prevent="form.post('/contact')">
        <input v-model="form.name" :aria-invalid="!!form.errors.name" />
        <p v-if="form.errors.name">{{ form.errors.name }}</p>
        <!-- ... -->
    </form>
</template>
```

That's all there is to it. There's no validation code in the controller and no error
handling to write.

## How it works

1. `useForm` posts the form as JSON, or as multipart form data when it includes
   files. `#[MapRequestPayload]` accepts both.
2. `#[MapRequestPayload]` deserializes it into the DTO and validates it, as it does
   for any Symfony app.
3. If the DTO is invalid, Symfony throws an HTTP exception (`422` by default) that
   wraps a `ValidationFailedException` and its `ConstraintViolationList`.
4. For Inertia requests, the bundle catches that exception, stores the violations for
   the next request and redirects back. A redirect after `PUT`, `PATCH` or `DELETE`
   becomes a `303`, as all Inertia redirects do.
5. The next page receives the violations as its `errors` prop, which `useForm`
   reads into `form.errors`. Errors are sent once, like flash messages.

Rejected submissions aren't logged as exceptions: an invalid form isn't an
application error.

Requests that aren't Inertia visits, such as a JSON API calling the same route, keep
Symfony's normal `422` response. One action can serve both.

The redirect goes back to the `Referer`, then to the
[stored previous URL](/reference/configuration#store-previous-url), then to `/`,
like `$inertia->back()`.

## Field names

Errors are keyed by the violation's property path, written with dots, as Inertia
form fields are:

| Property path        | `errors` key     |
| -------------------- | ---------------- |
| `email`              | `email`          |
| `address.street`     | `address.street` |
| `items[0].name`      | `items.0.name`   |
| `[items][0][name]`   | `items.0.name`   |

Nested objects and collections are validated with the usual constraints. Mark
nested objects with `#[Assert\Valid]`, as you would anywhere in Symfony, and their
errors appear under paths like `address.street`.

A class-level constraint, such as a `Callback` comparing two fields, has no property
path unless you give it one. Use `atPath()` so its error lands on a field:

```php
#[Assert\Callback]
public function validate(ExecutionContextInterface $context): void
{
    if ($this->password !== $this->passwordConfirmation) {
        $context->buildViolation('The passwords do not match.')
            ->atPath('passwordConfirmation')
            ->addViolation();
    }
}
```

Without a path, the error is keyed by an empty string.

## Messages

Messages are the Validator's own: each constraint's message, with its placeholders
filled in. Change one on the constraint:

```php
#[Assert\NotBlank(message: 'Please tell us your name.')]
#[Assert\Length(min: 10, minMessage: 'Please write at least {{ limit }} characters.')]
```

With `symfony/translation` installed, messages are translated in the `validators`
domain into the request's locale, so you can reword them across the app or support
several languages from `translations/validators.<locale>.yaml`. See
[Translating constraint messages](https://symfony.com/doc/current/validation/translations.html).

### Every message per field

By default each field gets its first message, as a string. To send all of them, as
an array, turn on [`with_all_errors`](/reference/configuration#with-all-errors):

```yaml
evo_mark_inertia_symfony:
    with_all_errors: true
```

## Everything else `#[MapRequestPayload]` supports

The bundle only changes what happens when validation fails, so the rest of
Symfony's request mapping works as documented:

- **Validation groups:** `#[MapRequestPayload(validationGroups: ['registration'])]`,
  including groups computed with an expression.
- **Arrays of objects:** `#[MapRequestPayload(type: Item::class)] array $items`.
- **Query strings:** `#[MapQueryString]`, for example on a search form that submits
  with `GET`, is handled the same way.
- **Serializer context and custom resolvers**, via the attribute's options.

## Error bags

When a page has more than one form, give each form an error bag so their errors
don't mix:

```js
form.post("/login", { errorBag: "login" });
```

Inertia sends the bag name in the `X-Inertia-Error-Bag` header, and the form's errors
arrive under `errors.login`.

## Validating by hand

To validate in the controller instead, for example to validate an object you have
built yourself, call the validator and pass the violations to `withErrors()`:

```php
use Symfony\Component\Validator\Validator\ValidatorInterface;

$violations = $validator->validate($order);

if (\count($violations) > 0) {
    return $inertia->withErrors($violations)->back();
}
```

`withErrors()` also accepts field => message pairs, for errors that don't come from
the Validator:

```php
if (!$authenticated) {
    return $inertia->withErrors(['email' => 'These credentials do not match our records.'])->back();
}
```

Pass a bag name as the second argument to target one form: `withErrors($violations, 'login')`.

## Under the hood

Errors are kept in the session until the next page reads them, under
`EvoMark\InertiaSymfonyBundle\Support\SessionKey::ERRORS` (`inertia.errors`), as
`array<bag, array<field, list<string>>>`. `withErrors()` writes them there, and the
`errors` prop is resolved from them. Only a session the client already has is read,
so pages that never show errors don't start one. Writing the key yourself works,
but `withErrors()` is the supported way.
