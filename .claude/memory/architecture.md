# Architecture and CLI commands

Read before changing namespaces, entry points, the datacompliance plugins, traits or the CLI commands.

### Namespaces

- Backend: `Akeeba\Component\DataCompliance\Administrator\*`
- Frontend: `Akeeba\Component\DataCompliance\Site\*`
- Plugins: `Akeeba\Plugin\{Group}\DataCompliance\*`

### Entry Points

- `component/backend/services/provider.php` — DI service provider, the component bootstrap entry point.
- `component/backend/src/Model/WipeModel.php` — the core of account erasure.

### Plugin Architecture

Datacompliance-group plugins handle data export/deletion for specific subsystems, implementing event handlers invoked by `RunPluginsTrait`. The `ars` and `ats` plugins integrate with Akeeba Release System and Akeeba Ticket System respectively.

### Key Patterns

- **Composition via traits** (`component/backend/src/Mixin/`) — `RunPluginsTrait`, `TriggerEventTrait`, `ControllerEventsTrait`, etc.
- **Composer autoloader** — loaded in `Extension/DataComplianceComponent.php` for the `akeeba/s3` dependency (vendor dir: `component/backend/vendor/`)

## CLI Commands

Registered via the console plugin, invoked through Joomla CLI:
- `datacompliance:lifecycle:delete` — auto-remove inactive users
- `datacompliance:lifecycle:notify` — notify users before deletion
- `datacompliance:account:delete` — manual account deletion
