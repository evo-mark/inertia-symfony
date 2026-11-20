---
layout: home

hero:
  name: "Inertia Symfony"
  text: "The Inertia.js adapter for Symfony"
  tagline: Build single-page apps with Vue, React or Svelte, using Symfony controllers and routing.
  actions:
    - theme: brand
      text: Get started
      link: /guide/getting-started
    - theme: alt
      text: Configuration
      link: /reference/configuration

features:
  - title: Controllers, not an API
    details: Return `$inertia->render('Component', $props)` from any controller and keep Symfony's routing, security and forms.
  - title: Every Inertia prop type
    details: Shared, optional, deferred, merge, once, always and scroll props, with partial reloads handled for you.
  - title: Server-side rendering
    details: Render pages on a Node SSR server with automatic fallback, failure events and an opt-in exception.
  - title: Symfony native
    details: Type-hint InertiaInterface anywhere, validate with #[MapRequestPayload], and use session flash data and a Twig root template.
---
