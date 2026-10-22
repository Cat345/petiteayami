import { execFileSync } from 'child_process';

/**
 * Runs a WP-CLI command against the fk-lane-1 Local site, with the PHP
 * memory/socket overrides this Local install needs (system PHP looks for
 * MySQL at the wrong socket path and the default 128M memory limit is too
 * small for this plugin-heavy site).
 */
const WP_PATH = '/Users/amansaxena/Local Sites/fk-lane-1/app/public';
const WP_SOCK =
	'/Users/amansaxena/Library/Application Support/Local/run/byts3S23c/mysql/mysqld.sock';
const WP_BIN = '/usr/local/bin/wp';
// `wp db query` shells out to the `mysql` CLI, which isn't on PATH by
// default - Local bundles it under its lightning-services folder.
const MYSQL_BIN_DIR =
	'/Users/amansaxena/Library/Application Support/Local/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin';

export function wpCli( args: string[] ): string {
	return execFileSync(
		'php',
		[
			'-d',
			'memory_limit=512M',
			'-d',
			`mysqli.default_socket=${ WP_SOCK }`,
			'-d',
			`pdo_mysql.default_socket=${ WP_SOCK }`,
			WP_BIN,
			`--path=${ WP_PATH }`,
			...args,
		],
		{
			encoding: 'utf-8',
			env: { ...process.env, PATH: `${ MYSQL_BIN_DIR }:${ process.env.PATH }` },
		}
	);
}

export function wpEval( phpCode: string ): string {
	return wpCli( [ 'eval', phpCode ] );
}
