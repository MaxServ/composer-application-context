<?php
namespace MaxServ\ComposerApplicationContext;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Plugin\PluginInterface;


class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return [
            'post-autoload-dump' => [
                ['onPostAutoloadDump', -21]
            ],
        ];
    }

    public function onPostAutoloadDump()
    {
        $config = null;

        $package = $this->composer->getRepositoryManager()->findPackage('maxserv/composer-application-context', '*');

        if ($package instanceof Package && array_key_exists('application-context', $package->getExtra())) {
            $config = $package->getExtra()['application-context'];
        }

        if ($config !== null && array_key_exists('application-context', $this->composer->getPackage()->getExtra())) {
            $config = array_merge_recursive($config, $this->composer->getPackage()->getExtra()['application-context']);
        }

        $this->prefixSourceFiles(
            $config['paths'],
            $this->buildPhpPrefixSnippet($config['variables'])
        );
    }

    protected function prefixSourceFiles(array $paths, string $snippet)
    {
        $boundary = '// --- ' . md5(self::class) . ' ---';

        $snippet = $boundary . PHP_EOL . $snippet . PHP_EOL . $boundary;

        $this->io->write(
            'Prefixing source files with: ' . PHP_EOL . $snippet
        );

        array_walk(
            $paths,
            function (string $path) use ($boundary, $snippet) {
                $contents = null;

                if(is_file($path)) {
                    $contents = file_get_contents($path);
                }

                if ($contents !== null && $contents !== false && strpos($contents, $boundary) !== false) {
                    $contents = preg_replace(
                        '/' .
                            addcslashes(preg_quote($boundary), '/') .
                            '(.*)' .
                            addcslashes(preg_quote($boundary), '/') . '\s*' .
                        '/ms',
                        '',
                        $contents
                    );
                }

                if ($contents !== null && $contents !== false) {
                    $contents = preg_replace(
                        '/<\?php\s/',
                        '<?php' . PHP_EOL . $snippet . PHP_EOL,
                        $contents
                    );

                    if (file_put_contents($path, $contents) !== false) {
                        $this->io->write('Patched "' . $path . '" with getenv/putenv"');
                    }
                }
            }
        );
    }

    protected function buildPhpPrefixSnippet(array $variables)
    {
        $variableSnippets = [];

        foreach ($variables as $key => $value) {
            if (!is_string($value)) {
                $this->io->writeError(
                    'Value for "' . $key . '" is of type "' . gettype($value) . '" ' .
                    'while a string is expected!'
                );
            } else {
                /**
                 * Check if $value is not an empty string at the time of execution
                 * AND doesn't contain `%env(` which might be
                 * because of replacement gone wrong.
                 */
                $variableSnippets[] = 'if (\'' . $value . '\' !== \'\' && stripos(\'' . $value . '\', \'%env(\') === false) {';

                $variableSnippets[] = '  if (getenv(\'' . $key . '\') === false)' .
                    '{putenv(\'' . $key . '=' . $value . '\');}';

                $variableSnippets[] = '  if ($_SERVER[\'' . $key . '\'] === null)' .
                    '{$_SERVER[\'' . $key . '\'] = \'' . $value . '\';}';

                $variableSnippets[] = '}';
            }
        }

        /**
         * Check if both functions `getenv` and `putenv` exist before possibly invoking them
         */
        return '// Prefixed by ' . self::class .PHP_EOL .
            'call_user_func(function(){' . PHP_EOL .
            'if (function_exists(\'getenv\') !== false && function_exists(\'putenv\') !== false){' . PHP_EOL .
            implode(PHP_EOL, $variableSnippets) . PHP_EOL . '}' . PHP_EOL . '});';
    }
}
