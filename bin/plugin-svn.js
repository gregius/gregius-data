#!/usr/bin/env node

const fs = require( 'fs' );
const path = require( 'path' );
const { execSync } = require( 'child_process' );

const ROOT_DIR = path.resolve( __dirname, '..' );
const PLUGIN_SLUG = 'gregius-data';
const SVN_URL = `https://plugins.svn.wordpress.org/${ PLUGIN_SLUG }`;
const SVN_DIR = path.join( ROOT_DIR, 'build', 'svn' );

const SVN_ASSETS = [];

const EXCLUDE_PATTERNS = [
	'.git',
	'.github',
	'.artifacts',

	// Node.js / npm
	'node_modules',
	'package.json',
	'package-lock.json',

	// Assets source and npm
	'assets/src',
	'assets/node_modules',
	'assets/package.json',
	'assets/package-lock.json',
	'assets/webpack.config.js',
	'assets/dashboard.js',
	'assets/editor.js',
	'assets/frontend.js',
	'assets/.gitignore',

	// Block source files
	'blocks/rag-assistant/src',
	'blocks/rag-assistant/node_modules',
	'blocks/rag-assistant/package.json',
	'blocks/rag-assistant/package-lock.json',
	'blocks/rag-assistant/.gitignore',

	// Documentation
	'docs',
	'README.md',
	'assets/README.md',
	'CHANGELOG.md',

	// Git and IDE
	'.gitignore',
	'.gitattributes',
	'.idea',
	'.vscode',

	// Build output
	'build',
	'dist',

	// Build scripts
	'bin',

	// OS files
	'*.DS_Store',
	'*Thumbs.db',
	'*.swp',
	'*.swo',

	// Logs and temp
	'*.log',
	'*.bak',
	'*.tmp',

	// Test files and dev dependencies
	'tests',
	'phpunit.xml',
	'phpunit.xml.dist',
	'phpunit-wp.xml.dist',
	'.phpunit.result.cache',
	'vendor',
	'composer.json',
	'composer.lock',

	// Markdown and zips
	'*.md',
	'*.zip',
];

function shouldExclude( filePath ) {
	const normalizedPath = filePath.replace( /\\/g, '/' );
	const pathParts = normalizedPath.split( '/' );
	const fileName = path.basename( normalizedPath );

	for ( const pattern of EXCLUDE_PATTERNS ) {
		if ( pattern.includes( '*' ) ) {
			if ( pattern.startsWith( '*.' ) ) {
				const extension = pattern.substring( 1 );
				if ( fileName.endsWith( extension ) ) {
					return true;
				}
			} else {
				const regex = new RegExp( '^' + pattern.replace( /\*/g, '.*' ).replace( /\//g, '\\/' ) );
				if ( regex.test( fileName ) || regex.test( normalizedPath ) ) {
					return true;
				}
			}
			continue;
		}
		if ( normalizedPath === pattern ) {
			return true;
		}
		if ( normalizedPath.startsWith( pattern + '/' ) ) {
			return true;
		}
		if ( pathParts[ 0 ] === pattern && ! pattern.includes( '/' ) ) {
			return true;
		}
	}
	return false;
}

function getVersion() {
	const pluginFile = fs.readFileSync( path.join( ROOT_DIR, `${ PLUGIN_SLUG }.php` ), 'utf-8' );
	const match = pluginFile.match( /^\s*\*\s*Version:\s*(.+)/m );
	if ( ! match ) {
		throw new Error( 'Version not found in plugin header' );
	}
	return match[ 1 ].trim();
}

function svnCredentials() {
	const user = process.env.WP_SVN_USERNAME;
	const pass = process.env.WP_SVN_PASSWORD;
	if ( user && pass ) {
		return `--username ${ user } --password ${ pass } --no-auth-cache`;
	}
	return '';
}

function svn( cmd, cwd = SVN_DIR ) {
	const creds = svnCredentials();
	const fullCmd = creds ? `svn ${ cmd } ${ creds }` : `svn ${ cmd }`;
	return execSync( fullCmd, { cwd, encoding: 'utf-8', stdio: 'pipe' } ).trim();
}

function svnSilent( cmd, cwd = SVN_DIR ) {
	try { return svn( cmd, cwd ); } catch { return ''; }
}

function ensureSvnRepo() {
	const svnMeta = path.join( SVN_DIR, '.svn' );
	if ( ! fs.existsSync( svnMeta ) ) {
		if ( fs.existsSync( SVN_DIR ) ) {
			fs.rmSync( SVN_DIR, { recursive: true, force: true } );
		}
		fs.mkdirSync( SVN_DIR, { recursive: true } );

		const creds = svnCredentials();
		const cmd = creds
			? `svn co ${ SVN_URL } . ${ creds }`
			: `svn co ${ SVN_URL } .`;
		execSync( cmd, { cwd: SVN_DIR, stdio: 'inherit' } );
	} else {
		console.log( 'Updating SVN working copy...' );
		execSync( 'svn up', { cwd: SVN_DIR, stdio: 'inherit' } );
	}
}

function copyPluginToTrunk() {
	const trunkDir = path.join( SVN_DIR, 'trunk' );

	if ( fs.existsSync( trunkDir ) ) {
		const entries = fs.readdirSync( trunkDir );
		for ( const entry of entries ) {
			const entryPath = path.join( trunkDir, entry );
			fs.rmSync( entryPath, { recursive: true, force: true } );
		}
	} else {
		fs.mkdirSync( trunkDir, { recursive: true } );
	}

	function walkAndCopy( srcDir, basePath = ROOT_DIR ) {
		const items = fs.readdirSync( srcDir );
		for ( const item of items ) {
			const srcPath = path.join( srcDir, item );
			const relativePath = path.relative( basePath, srcPath );

			if ( shouldExclude( relativePath ) ) {
				continue;
			}

			const stats = fs.statSync( srcPath );
			if ( stats.isDirectory() ) {
				walkAndCopy( srcPath, basePath );
			} else if ( stats.isFile() ) {
				const destPath = path.join( trunkDir, relativePath );
				const destDir = path.dirname( destPath );
				if ( ! fs.existsSync( destDir ) ) {
					fs.mkdirSync( destDir, { recursive: true } );
				}
				fs.copyFileSync( srcPath, destPath );
			}
		}
	}

	walkAndCopy( ROOT_DIR );
	console.log( 'Plugin files copied to trunk.' );
}

function copyAssetsToSvnAssets() {
	const svnAssetsDir = path.join( SVN_DIR, 'assets' );
	if ( ! fs.existsSync( svnAssetsDir ) ) {
		fs.mkdirSync( svnAssetsDir, { recursive: true } );
	}

	const pluginAssetsDir = path.join( ROOT_DIR, 'assets' );
	let count = 0;
	for ( const asset of SVN_ASSETS ) {
		const src = path.join( pluginAssetsDir, asset );
		if ( fs.existsSync( src ) ) {
			fs.copyFileSync( src, path.join( svnAssetsDir, asset ) );
			count++;
		}
	}
	console.log( `${ count } asset files copied to SVN assets.` );
}

function setMimeTypes() {
	const svnAssetsDir = path.join( SVN_DIR, 'assets' );
	if ( ! fs.existsSync( svnAssetsDir ) ) {
		return;
	}

	const files = fs.readdirSync( svnAssetsDir );
	for ( const f of files ) {
		if ( f.endsWith( '.png' ) ) {
			svnSilent( `propset svn:mime-type image/png assets/${ f }` );
		} else if ( f.endsWith( '.svg' ) ) {
			svnSilent( `propset svn:mime-type image/svg+xml assets/${ f }` );
		}
	}
	console.log( 'SVN mime-type properties set on images.' );
}

function tagRelease( version ) {
	const tagDir = path.join( SVN_DIR, 'tags', version );
	if ( fs.existsSync( tagDir ) ) {
		console.log( `Tag ${ version } already exists — skipping.` );
		return false;
	}
	execSync( `svn cp trunk tags/${ version }`, { cwd: SVN_DIR, stdio: 'inherit' } );
	console.log( `Tagged release ${ version }.` );
	return true;
}

function svnAddNewFiles() {
	console.log( 'Adding new files to SVN...' );

	const statusBefore = svnSilent( 'status trunk assets tags' );
	const unversioned = statusBefore
		.split( '\n' )
		.filter( ( line ) => line.startsWith( '?' ) )
		.map( ( line ) => line.substring( 8 ).trim() );

	if ( unversioned.length > 0 ) {
		for ( const file of unversioned ) {
			console.log( `  Adding: ${ file }` );
			svnSilent( `add "${ file }"` );
		}
	}

	console.log( `${ unversioned.length } files added.` );
}

function svnDeleteRemoved() {
	const status = svnSilent( 'status trunk assets tags' );
	const missing = status
		.split( '\n' )
		.filter( ( line ) => line.startsWith( '!' ) )
		.map( ( line ) => line.substring( 8 ).trim() );

	if ( missing.length > 0 ) {
		for ( const file of missing ) {
			console.log( `  Deleting: ${ file }` );
			svnSilent( `delete "${ file }"` );
		}
		console.log( `${ missing.length } files marked for deletion.` );
	}
}

function svnCommit( version ) {
	const creds = svnCredentials();
	const cmd = creds
		? `svn ci -m "Release ${ version }" ${ creds }`
		: `svn ci -m "Release ${ version }"`;
	console.log( `Committing release ${ version }...` );
	execSync( cmd, { cwd: SVN_DIR, stdio: 'inherit' } );
}

async function main() {
	const version = getVersion();
	const commit = process.argv.includes( '--commit' );

	console.log( `\n${ commit ? 'Publishing' : 'Preparing' } ${ PLUGIN_SLUG } v${ version } for WordPress.org SVN...\n` );

	ensureSvnRepo();
	copyPluginToTrunk();
	copyAssetsToSvnAssets();
	setMimeTypes();
	const tagged = tagRelease( version );
	svnAddNewFiles();
	svnDeleteRemoved();

	console.log( '\nSVN status:' );
	execSync( 'svn status', { cwd: SVN_DIR, stdio: 'inherit' } );

	if ( commit ) {
		svnCommit( version );
		console.log( `\nRelease ${ version } committed.` );
		console.log( `Plugin page: https://wordpress.org/plugins/${ PLUGIN_SLUG }` );
	} else {
		console.log( `\nDry-run complete. Run with --commit to publish.` );
	}
}

main().catch( ( err ) => {
	console.error( `Error: ${ err.message }` );
	process.exit( 1 );
} );
