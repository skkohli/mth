# Docker Runtime

Start the local WordPress stack:

```sh
docker compose up -d
```

Open the site at:

```text
http://localhost:8081
```

The MariaDB container imports `dbdump/u813440994_J22Y4 (3).sql` only when the `db_data` volume is first created. To re-import from the dump, remove that volume and start again:

```sh
docker compose down -v
docker compose up -d
```

Optional full URL rewrite for serialized content:

```sh
docker compose --profile tools run --rm wpcli wp search-replace 'https://mytrustedhomes.com' 'http://localhost:8081' --skip-columns=guid --allow-root
```
