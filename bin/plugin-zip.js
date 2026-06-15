#!/usr/bin/env node

/**
 * Build Plugin Zip
 * 
 * Creates a production-ready WordPress.org compatible zip file.
 * Cross-platform compatible (Windows, macOS, Linux).
 * 
 * Usage: node bin/build-plugin-zip.js
 */

const fs = require( 'fs' );
const path = require( 'path' );
const archiver = require( 'archiver' );

// Plugin root directory
const ROOT_DIR = path.resolve( __dirname, '..' );
const PLUGIN_SLUG = 'gregius-data';
const OUTPUT_FILE = path.join( ROOT_DIR, 'build', 'zip', `${ PLUGIN_SLUG }.zip` );

/**
 * Files and directories to exclude from the zip
 * Paths should be relative to plugin root
 */
const EXCLUDE_PATTERNS = [
	// Development directories
	'.git',
	'.github',
	'.artifacts',

	// Root-level package files (build script dependencies)
	'package.json',
	'package-lock.json',
	'node_modules',

	// Source files (we KEEP assets/build/ and blocks/*/build/)
	'assets/src',
	'assets/node_modules',
	'assets/package.json',
	'assets/package-lock.json',
	'assets/webpack.config.js',
	'assets/dashboard.js',
	'assets/editor.js',
	'assets/frontend.js',

	// Block source files (rag-assistant block)
	'blocks/rag-assistant/src',
	'blocks/rag-assistant/node_modules',
	'blocks/rag-assistant/package.json',
	'blocks/rag-assistant/package-lock.json',
	'blocks/rag-assistant/.gitignore',

	// Hidden files in assets
	'assets/.gitignore',

	// Documentation files (entire docs folder)
	'docs',
	'README.md',
	'assets/README.md',
	'CHANGELOG.md',

	// Git files
	'.gitignore',
	'.gitattributes',

	// Build output directory (where zip is created) - ROOT LEVEL ONLY
	'build',
	'dist',

	// Build script itself
	'bin',

	// OS files (wildcard patterns)
	'*.DS_Store',
	'*Thumbs.db',

	// IDE files
	'.idea',
	'.vscode',
	'*.swp',
	'*.swo',

	// Logs and temp files (wildcard patterns)
	'*.log',
	'*.bak',
	'*.tmp',
	'*.md',
	'*.zip',

	// Test files
	'tests',
	'phpunit.xml',
	'phpunit.xml.dist',
	'phpunit-wp.xml.dist',
	'.phpunit.result.cache',

	// Dev dependencies and metadata
	'vendor',
	'composer.json',
	'composer.lock',
];

/**
 * Check if a file path should be excluded
 * 
 * @param {string} filePath - The file path to check (relative to plugin root)
 * @return {boolean} True if the file should be excluded
 */
function shouldExclude( filePath ) {
	// Normalize path for cross-platform comparison
	const normalizedPath = filePath.replace( /\\/g, '/' );
	const pathParts = normalizedPath.split( '/' );
	const fileName = path.basename( normalizedPath );
	
	for ( const pattern of EXCLUDE_PATTERNS ) {
		// Wildcard pattern match (e.g., *.log, *.DS_Store)
		if ( pattern.includes( '*' ) ) {
			// For extension patterns like *.log, only match the actual extension
			if ( pattern.startsWith( '*.' ) ) {
				const extension = pattern.substring( 1 ); // Get the extension part like '.log'
				if ( fileName.endsWith( extension ) ) {
					return true;
				}
			} else {
				// For other wildcard patterns, use regex
				const regex = new RegExp( '^' + pattern.replace( /\*/g, '.*' ).replace( /\//g, '\\/' ) );
				if ( regex.test( fileName ) || regex.test( normalizedPath ) ) {
					return true;
				}
			}
			continue;
		}
		
		// Exact match (full path equals pattern)
		if ( normalizedPath === pattern ) {
			return true;
		}
		
		// Directory match (path starts with pattern/)
		// This handles nested paths like "assets/src" or ".git"
		if ( normalizedPath.startsWith( pattern + '/' ) ) {
			return true;
		}
		
		// Root-level directory only (for patterns like "build", "bin", "tests")
		// Only match if it's the first path component
		if ( pathParts[ 0 ] === pattern && ! pattern.includes( '/' ) ) {
			return true;
		}
	}
	
	return false;
}

/**
 * Create the plugin zip file
 */
async function createZip() {
	console.log( 'Building Gregius Data plugin zip...\n' );
	
	// Ensure build directory exists
	const buildDir = path.join( ROOT_DIR, 'build', 'zip' );
	if ( ! fs.existsSync( buildDir ) ) {
		fs.mkdirSync( buildDir, { recursive: true } );
	}
	
	// Remove existing zip if it exists
	if ( fs.existsSync( OUTPUT_FILE ) ) {
		fs.unlinkSync( OUTPUT_FILE );
		console.log( 'Removed existing zip file\n' );
	}
	
	// Create write stream
	const output = fs.createWriteStream( OUTPUT_FILE );
	const archive = archiver( 'zip', {
		zlib: { level: 9 }, // Maximum compression
	} );
	
	// Track statistics
	let filesAdded = 0;
	let filesSkipped = 0;
	
	// Listen for archive events
	output.on( 'close', () => {
		const sizeInMB = ( archive.pointer() / 1024 / 1024 ).toFixed( 2 );
		console.log( `\n✅ Plugin zip created successfully!` );
		console.log( `   File: ${ OUTPUT_FILE }` );
		console.log( `   Size: ${ sizeInMB } MB` );
		console.log( `   Files included: ${ filesAdded }` );
		console.log( `   Files excluded: ${ filesSkipped }` );
		console.log( `\n   Upload to WordPress.org or test locally.` );
	} );
	
	archive.on( 'warning', ( err ) => {
		if ( err.code === 'ENOENT' ) {
			console.warn( '⚠️  Warning:', err.message );
		} else {
			throw err;
		}
	} );
	
	archive.on( 'error', ( err ) => {
		throw err;
	} );
	
	// Pipe archive to output file
	archive.pipe( output );
	
	// Add files to archive
	console.log( 'Adding files to zip...\n' );
	
	/**
	 * Recursively add directory to archive
	 * 
	 * @param {string} dirPath - Directory path to process
	 * @param {string} basePath - Base path for calculating relative paths
	 */
	function addDirectory( dirPath, basePath = ROOT_DIR ) {
		const items = fs.readdirSync( dirPath );
		
		for ( const item of items ) {
			const fullPath = path.join( dirPath, item );
			const relativePath = path.relative( basePath, fullPath );
			const zipPath = path.join( PLUGIN_SLUG, relativePath );
			
			// Check if should exclude
			if ( shouldExclude( relativePath ) ) {
				filesSkipped++;
				continue;
			}
			
			const stats = fs.statSync( fullPath );
			
			if ( stats.isDirectory() ) {
				addDirectory( fullPath, basePath );
			} else if ( stats.isFile() ) {
				archive.file( fullPath, { name: zipPath } );
				filesAdded++;
				
				// Show progress for important files
				if ( relativePath.endsWith( '.php' ) || relativePath.endsWith( 'readme.txt' ) ) {
					console.log( `  ✓ ${ relativePath }` );
				}
			}
		}
	}
	
	// Start adding files from root
	addDirectory( ROOT_DIR );
	
	// Finalize the archive
	await archive.finalize();
}

// Run the build
createZip().catch( ( err ) => {
	console.error( '\n❌ Error creating zip:', err.message );
	process.exit( 1 );
} );
