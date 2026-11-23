# FreeSWITCH Modules Page

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## FreeSWITCH Modules Page

- Module config writes should target `modules.conf.xml` under the FreeSWITCH conf dir from default settings category `switch`, subcategory `conf`, name `dir`; do not depend on `session('switch.conf.dir')`.
- Module disk sync should scan the module dir from default settings category `switch`, subcategory `mod`, name `dir`.
- When module autoload settings change, rewrite `/autoload_configs/modules.conf.xml` and run `reloadxml` through `FreeswitchEslService`.
- Start and stop actions should use `FreeswitchEslService` commands (`load mod_name`, `unload mod_name`) and then refresh runtime status from `show modules as json`; allow for a short delay before the runtime list reflects the command.
- Treat FreeSWITCH `-ERR` responses as real action failures and surface the ESL text to the user. Some modules return useful errors such as `Module is not unloadable.`
- `Notification.vue` expects a message-bag shape where each key maps to an array and renders the first value for each key, for example `{ error: ["FreeSWITCH returned an error."], error_1: ["mod_x: Module is not unloadable."] }`.
- Runtime start/stop buttons should reflect current module status: show Start only for stopped modules, Stop only for running modules, and hide both when status is unknown.

