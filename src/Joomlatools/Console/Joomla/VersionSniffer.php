<?php
    /**
     * @copyright      Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
     * @license        Mozilla Public License, version 2.0
     * @link           http://github.com/joomlatools/joomlatools-console for the canonical source repository
     */

    namespace Joomlatools\Console\Joomla;

    use PhpParser\Node;
    use PhpParser\Node\Stmt\Const_;
    use PhpParser\Node\Stmt\Expression;
    use PhpParser\NodeFinder;
    use PhpParser\NodeVisitorAbstract;

    class VersionSniffer
    {
        public $release = 'unknown';

        public $major;
        public $minor;
        public $patch;
        public $extra;

        private function __construct(string $code)
        {
            $parser = (new \PhpParser\ParserFactory)->create(\PhpParser\ParserFactory::PREFER_PHP7);

            $ast = $parser->parse($code);

            $nodeFinder = new NodeFinder;
            $nodes = $nodeFinder->find($ast, function (Node $node) {
                return $node instanceof Node\Const_ || $node instanceof Node\Stmt\PropertyProperty;
            });
            foreach ($nodes as $node) {
                $value = $node instanceof Node\Stmt\PropertyProperty ? $node->default : $node->value;
                switch ((string)$node->name) {
                    case 'RELEASE':
                        // v3.5 <= version < 4.0
                        [$major, $minor] = explode('.', $value->value);
                        $this->major = (int)$major;
                        $this->minor = (int)$minor;
                        break;
                    case 'MAJOR_VERSION':
                        $this->major = (int)$value->value;
                        break;
                    case 'MINOR_VERSION':
                        $this->minor = (int)$value->value;
                        break;
                    case 'PATCH_VERSION':
                    case 'DEV_LEVEL':
                        $this->patch = (int)$value->value;
                        break;
                    case 'EXTRA_VERSION':
                    case 'BUILD':
                        $this->extra = $value->value;
                        break;
                }
            }
            $this->release = $this->version();
        }

        public static function fromFile(string $file): self
        {
            if (!is_file($file)) {
                throw new \RuntimeException("File {$file} not found");
            }

            return new static(file_get_contents($file));
        }

        public function version(): string
        {
            if (empty($this->major)) {
                return 'unknown';
            }

            return rtrim(
                implode('.', [$this->major, $this->minor, $this->patch]) . '-' . $this->extra,
                '-'
            );
        }

        public function major(): ?int
        {
            return $this->major;
        }

        public function minor(): ?int
        {
            return $this->minor;
        }

        public function patch(): ?int
        {
            return $this->patch;
        }

        public function extra(): ?string
        {
            return $this->extra;
        }
    }