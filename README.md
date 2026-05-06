# trackr

Personal library management, reading/working/todo trackings

## Install

1. Clone the repository
1. Copy .env.example as .env
1. Generate random api key for TYPESENSE_API_KEY
1. ``cd trackr``
1. ``docker compose up``
1. ``composer install``

### Init Typesense

```shell
php src/scripts/typesense-init-highlights.php
```

## Themes and Used Libraries

- Theme: https://usebootstrap.com/theme/tinydash
- League\Commonmark for markdown https://commonmark.thephpleague.com/

## Contributing

Please feel free to contribute.

## License

Distributed under the MIT License. See [LICENSE](LICENSE) for more information.