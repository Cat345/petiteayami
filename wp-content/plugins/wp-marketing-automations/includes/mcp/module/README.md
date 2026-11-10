# FunnelKit MCP module

The module registers the FunnelKit ability packs through the Abilities API and
boots the **FunnelKit AI connector** (`connector/`, entry class
`FKMCP_Connector`): the OAuth sign-in the FunnelKit AI gateway uses and the MCP
endpoint it calls, all under `/wp-json/funnelkit/`. The abilities are called
in-process by the connector and are never exposed over core REST
(`show_in_rest` is false). Everything else - rate limiting, auditing, logging,
pack helpers, settings screens - is plugin code in each host (`includes/mcp/`)
behind the `FKMCP_Host` adapter.

Since 2.0.0 the module no longer creates an MCP server on the MCP adapter and
no longer ships its own OAuth server (removed in 2.0.0: the old MCP-adapter
server, the module's OAuth, `fkmcp_enabled`, `fkmcp_oauth_*`). The
`/wp-json/funnelkit/` routes are now the FunnelKit AI connector's. The switch is
the connector's `fkai_enabled` ("Allow AI connections").

## Ownership

This directory in `wp-marketing-automations` is the single source of the module.
Funnel Builder copies it with its `bin/sync-mcp-module.sh` (PHP text domain
rewritten to `funnel-builder`, other files as is) and checks drift with
`bin/sync-mcp-module.sh --check`. Never edit Funnel Builder's copy by hand. The
literal `'wp-marketing-automations'` may appear in the module PHP only as a
text-domain argument (`, 'wp-marketing-automations' )`), because the sync
rewrites it.

## Files

- `fkmcp-bootstrap.php` - `fkmcp_register_module( $version, $path, $host )`;
  highest version boots on `plugins_loaded:0`, every candidate's host adapter
  is registered (`FKMCP_Hosts`).
- `class-fkmcp-module.php` - boots the hosts and the connector, decides which
  requests register the abilities (`needs_abilities()`).
- `class-fkmcp-registry.php` - collects packs, registers abilities (wrapping
  each callback through the primary host), the transport gate and the server
  instructions the connector hands out on `initialize`.
- `class-fkmcp-host.php` - host contract: slug/name/priority, `can_read`,
  `can_write`, `transport_gate`, `wrap_ability`, `log`, Pro/licence state,
  `register()` (packs, settings entry, REST routes).
- `class-fkmcp-pack.php` - pack contract + `FKMCP_API_VERSION`.
- `connector/` - the FunnelKit AI connector (`FKMCP_Connector`, entry class;
  the rest autoload on a connector request): OAuth discovery, authorize and
  consent pages, token endpoint, Bearer auth, grants table (`fkai_grants`) and
  the MCP endpoint `funnelkit/mcp` (see "The connector"). Stands down when the
  standalone FunnelKit AI plugin (`FKAI_VERSION`) is active.
- `fkmcp-uninstall.php` - removes the module's and the connector's data once no
  other copy of the module remains.

## Language

Two audiences, two rules:

- **Instructions to the model stay in English, untranslated.** Ability
  descriptions, JSON-schema descriptions and enum text are the tool spec a
  client reasons over, the same way a REST API's field names are; a site
  running in another locale must not hand its model a different spec. The
  operational errors an ability answers with ("No funnel with that id.")
  belong to the same surface and follow the same rule.
- **Text a person will read is translated.** Ability labels and pack names,
  which the settings screen lists; the consent screen; and the messages a
  client is expected to relay verbatim to its user - the Pro paywall and the
  rate-limit refusal - all go through `__()`.

The review that prompted this note recommended keeping every model-facing
string English, including the paywall and the rate limit. Those two are
deliberately left translated: a client repeats them to the person who has to
renew a licence or wait, so they are user-facing text carried by the model,
not instructions to it.

## Hook order

- `plugins_loaded:0` - each bundled copy registers a candidate; the highest
  version boots, every candidate's host adapter is added to it, and
  `FKMCP_Connector::init()` hooks the connector.
- `rest_api_init:20` - the connector registers its `funnelkit/*` routes; the
  hosts register their settings routes.
- `determine_current_user:25` - Bearer authentication on `funnelkit/mcp`,
  after core's Application Password check at 20.
- `wp_abilities_api_categories_init` / `wp_abilities_api_init` - the category
  and the abilities register, when `needs_abilities()` says so.

## Which requests load the abilities

`FKMCP_Module::needs_abilities()` keeps the funnelkit abilities (and every
pack's code) off requests that cannot use them:

- WP-CLI: only `eval`, `eval-file` and `shell` (a shell already controls the
  site, so the switch does not apply).
- AI connections off (`fkai_enabled` is not `yes`): nothing.
- Otherwise every non-REST request (admin, AJAX, in-process
  `rest_do_request()` - nothing is built unless something asks) and, over
  REST, only `FKMCP_Module::ROUTES` (`funnelkit/mcp`,
  `funnelkit/oauth/authorize`, `funnelkit/oauth/consent`) and each host's
  settings namespace (for example `autonami-app/mcp`).

Any other caller that reads these abilities must say so through the filter
(`funnelkit_needs_abilities` is an alias, applied after it):

```php
add_filter( 'fkmcp_needs_abilities', '__return_true' );
```

The module itself boots only on wp-admin, AJAX, WP-CLI, `funnelkit/*` routes
and the hosts' settings namespaces (`fkmcp_module_should_boot()`).

## The connector

Routes, all under `/wp-json/funnelkit/` and served for the pinned FunnelKit
AI gateway only (client `https://app.funnelkit.com/mcp/oauth/client.json`; filter
`fkai_clients`). It is built from `FKMCP_Connector_Clients::GATEWAY`, as is the
connect URL shown on the settings screen. Live stores never trust the staging
gateway; a test site opts it in with a mu-plugin:

```php
add_filter( 'fkai_clients', function ( $c ) {
	$c['https://app-staging.funnelkit.com/mcp/oauth/client.json'] = array(
		'name'         => 'FunnelKit AI (staging)',
		'redirect_uri' => 'https://app-staging.funnelkit.com/mcp/oauth/store-callback',
	);
	return $c;
} );
```

A grant from a client no longer
listed (the retired `mcp.funnelkit.ai` gateway, or staging on a site that has
not opted in) is refused at use, left out of
the connections list and deleted by the weekly purge; a refresh token only
refreshes under the client that obtained it.

| Route | What it does |
|---|---|
| `.well-known/oauth-protected-resource`, `.well-known/oauth-authorization-server` | Discovery. Answers while the switch is off. |
| `oauth/authorize` (GET) | Browser consent page. |
| `oauth/consent` (POST) | Records the administrator's approval. |
| `oauth/token` (POST) | Code and refresh exchange. HTTPS only unless `FKAI_ALLOW_HTTP`. |
| `oauth/revoke` (POST) | Revokes a token. |
| `mcp` (POST, Bearer) | `initialize`, `ping`, `tools/list` (paged at 120000 bytes), `tools/call`. While the switch is off it still answers the 401 sign-in challenge (discovery runs before the first approval turns it on); a signed-in `tools/list` or `tools/call` gets the `ai_disabled` tool error at HTTP 200. `tools/call` takes an ability's own name (`funnelkit/get-contact`) as well as the MCP spelling (`funnelkit-get-contact`). |
| `mcp/abilities` (GET, Bearer) | Ability discovery for the FunnelKit AI relay: the `funnelkit/*` abilities the token's user may run, sorted by name, `page` / `per_page` (100, at most 200) / `category`, no schemas. Each carries `name`, `label`, a one-line description of at most 500 characters (whole sentences), `category`, all three `annotations` (`read_only`, `destructive`, `idempotent`; unset reads as the unsafe value, a read-only ability as non-destructive and idempotent) and `meta` {`fkai_license`, `fkai_store`}. |
| `mcp/abilities/<vendor>/<ability>` (GET, Bearer) | One ability: the full description, `input_schema` and `output_schema` exactly as `tools/list` sends them, and the same annotations and `meta`. Errors use the WordPress REST shape: 401 `fkmcp_invalid_token`, 403 `fkmcp_forbidden` / `writes_paused` / `ai_disabled`, 404 `fkmcp_unknown_ability`, 400 `fkmcp_bad_request`, 429 `rate_limited`. |

Marker and who may connect:

- `.well-known/oauth-protected-resource` carries `"fkai_abilities": {"api_version": 1}`:
  the store serves the two ability routes. No URL - the relay derives them from
  the REST root it already validated, so a token never follows a store-supplied
  address.
- `.well-known/oauth-protected-resource` carries `"fkai_connector": 1`, so
  FunnelKit AI can tell this connector from the retired MCP-adapter server that
  used the same `funnelkit/mcp` path.
- `.well-known/oauth-protected-resource` also carries what the gateway looks
  the store's license up by: `fkai_url` (the gateway's field name; the
  value is `FKMCP_Connector_Urls::store_origin()`, no license is read on the
  store), the origin (scheme, host, port) of the domain, by the same rule FunnelKit Pro uses
  (`site_url()`, or the WPML / Polylang default-language home URL; a subsite
  answers with the main site's; omitted when it cannot be parsed), and, on
  multisite only, `fkai_network_id`.
- Administrators only (`manage_options`): the consent page, the code exchange,
  every `mcp` call and every refresh check it. A user who loses the capability
  gets 401 on the next call and `invalid_grant` on refresh, and the grant is
  deleted.
- The FunnelKit AI email: typed on FunnelKit AI, passed in the authorization
  code, stored per grant in `account_email` (beside `scopes`, default
  `mcp`). Display only, never verified.

Data names:

- Option `fkai_enabled` (`yes` = on). Turning it off revokes every grant.
  Other options: `fkai_db_version`, `fkai_site_id`, `fkai_site_home`.
- Table `{prefix}fkai_grants`: one row per approved connection, tokens stored
  hashed. Access token 1 hour, refresh token 30 days, refresh rotation with a
  60 second grace. Weekly cron `fkai_cleanup` prunes expired rows. A grant is
  revoked on user deletion, role change, switch off and the Disconnect buttons
  in the settings tab. The cron and user hooks are registered by the host
  (`BWFAN_Common::init`), not the module: the module never boots on wp-cron,
  the front end or core REST, where they fire.
- Network option `fkai_network_id` (multisite only): a random id created on
  first use, never regenerated and never deleted by uninstall - the gateway
  checks a subsite publishes the same id as its main site.
- Security events in the `mcp` log: `CONNECTOR_GRANT_CREATED`,
  `CONNECTOR_GRANT_REVOKED reason=admin|expired|reuse|capability`, `CONNECTOR_TOKEN_REVOKED`,
  `CONNECTOR_USER_REVOKED`, `CONNECTOR_ALL_REVOKED`, `CONNECTOR_SWITCH`.

## The ability gate

An MCP call passes three checks, in this order:

1. The host's request gate, `FKMCP_Registry::transport_gate()`: logged in (the
   Bearer token resolves the grant's user), read access, transport rate limit.
2. The ability's own permission callback: the pack gate plus the host's
   `can_read` / `can_write`. Writes are on by default and a user opts out with
   `fkmcp_allow_writes`.
3. The per-ability rate limit, 60 calls per minute per user.

A refusal comes back with `isError: true`, the message as `content[0].text` and
`structuredContent.error`, the FunnelKit AI gateway's contract: the gateway
never copies result text (it can hold site data) and exports only this object's
`message`, scrubbed of digits and hostnames, as `fkai.error_detail`.

```json
{ "error": { "code": "missing_argument", "reason": "fkmcp_invalid_input", "message": "funnel_id is required.", "data": { "status": 400, "ability": "funnelkit/create-step" } } }
```

- `code`: one of `FKMCP_Connector_Mcp::ERROR_CLASSES` - `missing_argument`,
  `invalid_argument`, `not_found`, `permission_denied`, `license_required`,
  `plugin_incompatible`, `conflict`, `rate_limited`, `result_too_large`,
  `internal_error` - so failures count by cause. Derived from the refusing code:
  the known ones by name (`ERROR_CLASS_OF`), the rest by suffix
  (`_not_found`, `_unavailable`, `_failed` ...), then by the HTTP status the
  registry attached, then by origin (a `fkmcp_*` code without a status refused
  the input; anything else is `internal_error`).
- `reason`: the refusing `WP_Error` code itself.
- `message`: engineers' text only, never site data - `error_detail()` strips
  tags, blanks quoted values, e-mail addresses and URLs, drops everything after
  the first colon (the lists of allowed values, ids and names) and caps it at
  `MESSAGE_MAX` (1000) characters, counted as characters so a translated
  message is not cut short in a multi-byte locale. Argument names and codes are
  fine; digits stay (the gateway scrubs them).
- `data`: the error data, minus the connector's private `fkai_code` /
  `fkai_message`, and always carrying `status` - the WP_Error's own, or the
  class's conventional one (`STATUS_OF_CLASS`) when it set none. It is never
  `{}`.

A pack that needs an exact class or message sets `fkai_code` / `fkai_message`
in its `WP_Error` data; both are consumed by the connector, never exported. A
tool that throws answers the same way (`internal_error` / `fkmcp_exception`,
message = the exception class and tool name), as a tool error, not a JSON-RPC
error. The gate's refusals (`ai_disabled`, `fkmcp_forbidden`, `fkmcp_rate_limited`,
`fkmcp_registry_unavailable`) carry it too; only malformed requests and
unknown tools remain JSON-RPC errors. A tool the user may not run is a tool
error, not "not found": `writes_paused` for a write while the host admits no
writes, `permission_denied` otherwise. Every tool error's `data` names the
`ability`.

Every schema `tools/list` and the ability detail send has a plain object root
(`type: object` with `properties`): root `anyOf` / `oneOf` / `allOf` fold into
one object (the root's own properties first) and `not` / `$ref` go, since one
composition keyword at a root makes Claude and ChatGPT drop every tool of the
server. The Abilities API still validates input against the ability's own
schema.

Every JSON-RPC result (`initialize`, `ping`, `tools/list`, `tools/call` and the
gate's refusals) carries `_meta.fkai_store`, so the gateway can trace any call
to the code that answered it without the session's `initialize`: the host's
`store_meta()` - `plugin`, `version`, `pro_version` and `license` (`lite`,
`pro`, or the Pro companion's own not-valid states such as `pro_expired`) -
plus `module`, the `FKMCP_VERSION` that booted. Nothing when the host returns
none. JSON-RPC errors (malformed request, unknown tool) have no result and so
carry none.

## The license mapping

Each ability says which FunnelKit license it needs, so the gateway can show it.
A pack definition may carry an optional `license` key:

```php
'license' => array( 'product' => 'fka' /* or 'fb', or array( 'fka', 'fb' ) */, 'level' => 'lite' /* or 'pro' */ ),
```

Without it the mapping is derived from the pack's `plugin_meta()` `id`
(`automations*` is `fka`, `funnel-builder*` is `fb`) and its `pro` flag, once
per pack per request. An unknown pack id or an invalid override is left
unmapped (with a `_doing_it_wrong` notice for the override), and the gateway
treats an unmapped ability as Pro. Product keys must be ones the FunnelKit AI
license team issued; the module cannot invent one.

`tools/list` items carry it as `_meta.fkai_license`. That is display data: the
gateway enforces its own reviewed allowlist, never this value. The `license`
key never reaches `wp_register_ability()`.

Known limits, for the release notes:

- Mixed module versions: a Funnel Builder or FunnelKit Automations host older
  than 2.0.2 running under the other plugin's 2.0.2–2.0.5 copy shows "Pro" as
  available on the consent screen whatever its license (2.0.6 shows no license rows). Ship both together.
- A subsite's membership proof needs the plugin active on the main site.
- The license origin is recomputed from the site URL on every request, so it
  drifts if the store's domain changes after the license was activated.

## Releasing

Bump the version in both `fkmcp_register_module()` calls
(`wp-marketing-automations.php` and `bin/sample.php`). Run the module's own
checks before tagging:

```bash
php tests/test-mcp-module.php
bash tests/connector/run.sh
```

On a version tie the copy registered first boots (`usort` is stable on PHP 8,
and `active_plugins` is alphabetical). This plugin's copy is registered as
2.0.0, so it boots over any 1.x copy another plugin still bundles.
