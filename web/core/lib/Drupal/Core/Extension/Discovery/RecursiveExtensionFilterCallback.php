<?php

namespace Drupal\Core\Extension\Discovery;

/**
 * Filters a RecursiveDirectoryIterator to discover extensions.
 *
 * To ensure the best possible performance for extension discovery, this
 * filter implementation hard-codes a range of assumptions about directories
 * in which Drupal extensions may appear and in which not. Every unnecessary
 * subdirectory tree recursion is avoided.
 *
 * The list of globally ignored directory names is defined in the
 * RecursiveExtensionFilterCallback::$skippedFolders property.
 *
 * In addition, all 'config' directories are skipped, unless the directory path
 * ends with 'modules/config', to still find the config module provided by
 * Drupal core and still allow that module to be overridden with a custom config
 * module.
 *
 * Lastly, ExtensionDiscovery instructs this filter to additionally skip all
 * 'tests' directories at regular runtime, since just with Drupal core only, the
 * discovery process yields 4x more extensions when tests are not ignored.
 *
 * @see ExtensionDiscovery::scan()
 * @see ExtensionDiscovery::scanDirectory()
 *
 * @internal
 */
class RecursiveExtensionFilterCallback {

  /**
   * List of base extension type directory names to scan.
   *
   * Only these directory names are considered when starting a filesystem
   * recursion in a search path.
   *
   * @var string[]
   */
  protected array $allowedExtensionTypes = [
    'profiles',
    'modules',
    'themes',
  ];

  /**
   * List of directory names to skip when recursing.
   *
   * These directories are globally ignored in the recursive filesystem scan;
   * i.e., extensions (of all types) are not able to use any of these names,
   * because their directory names will be skipped.
   *
   * @var string[]
   */
  protected array $skippedFolders = [
    // Object-oriented code subdirectories.
    'src',
    'lib',
    'vendor',
    // Front-end.
    'assets',
    'css',
    'files',
    'images',
    'js',
    'misc',
    'templates',
    // Legacy subdirectories.
    'includes',
    // Test subdirectories.
    'fixtures',
    // @todo ./tests/Drupal should be ./tests/src/Drupal
    'Drupal',
  ];

  /**
   * Construct a RecursiveExtensionFilterCallback.
   *
   * @param array $skipped_folders
   *   (optional) Add to the list of directories that should be filtered out
   *   during the iteration.
   * @param bool $accept_tests
   *   (optional) Pass FALSE to skip all test directories in the discovery. If
   *   TRUE, extensions in test directories will be discovered and only the
   *   global directory skip list in
   *   RecursiveExtensionFilterCallback::$skippedFolders and $skipped_folders
   *   are applied.
   */
  public function __construct(array $skipped_folders = [], bool $accept_tests = FALSE) {
    $this->skippedFolders = array_merge($this->skippedFolders, $skipped_folders);
    if (!$accept_tests) {
      $this->skippedFolders[] = 'tests';
    }
  }

  /**
   * Checks whether a given filesystem directory is acceptable.
   *
   * @param \RecursiveDirectoryIterator $filesystem_directory
   *   The filesystem directory to check.
   *
   * @return bool
   *   TRUE if the given filesystem directory is acceptable, otherwise FALSE.
   */
  public function accept(\RecursiveDirectoryIterator $filesystem_directory): bool {
    $name = $filesystem_directory->getFilename();
    // FilesystemIterator::SKIP_DOTS only skips '.' and '..', but not hidden
    // directories (like '.git').
    if ($name[0] === '.') {
      return FALSE;
    }
    if ($filesystem_directory->isDir()) {
      // If this is a subdirectory of a base search path, only recurse into the
      // fixed list of expected extension type directory names. Required for
      // scanning the top-level/root directory; without this condition, we would
      // recurse into the whole filesystem tree that possibly contains other
      // files aside from Drupal.
      if ($filesystem_directory->getSubPath() === '') {
        return in_array($name, $this->allowedExtensionTypes, TRUE);
      }
      // 'config' directories are special-cased here, because every extension
      // contains one. However, those default configuration directories cannot
      // contain extensions. The directory name cannot be globally skipped,
      // because core happens to have a directory of an actual module that is
      // named 'config'. By explicitly testing for that case, we can skip all
      // other config directories, and at the same time, still allow the core
      // config module to be overridden/replaced in a profile/site directory
      // (whereas it must be located directly in a modules directory).
      if ($name === 'config') {
        return str_ends_with($filesystem_directory->getPathname(), 'modules/config');
      }
      // Accept the directory unless the folder is skipped.
      return !in_array($name, $this->skippedFolders, TRUE);
    }
    // Only accept extension info files.
    return str_ends_with($name, '.info.yml');
  }

}
