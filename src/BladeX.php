<?php

namespace Spatie\BladeX;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Spatie\BladeX\Exceptions\CouldNotRegisterComponent;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\DomCrawler\Crawler;

class BladeX
{
    /** @var array */
    public $registeredComponents = [];

    public function component(string $componentName, string $viewName)
    {
        if (!view()->exists($viewName)) {
            throw CouldNotRegisterComponent::viewNotFound($componentName, $viewName);
        }

        $this->registeredComponents[$componentName] = $viewName;
    }

    public function getRegisteredComponents(): array
    {
        return $this->registeredComponents;
    }

    public function components(string $directory)
    {
        collect(File::allFiles($directory))
            ->filter(function (SplFileInfo $file) {
                return ends_with($file->getFilename(), '.blade.php');
            })
            ->each(function (SplFileInfo $fileInfo) {
                $componentName = str_replace_last('.blade.php', '', $fileInfo->getFilename());

                $componentName = kebab_case($componentName);

                $viewName = $this->getViewName($fileInfo->getPathname());

                $this->component($componentName, $viewName);
            });
    }

    private function getViewName(string $pathName): string
    {
        foreach (View::getFinder()->getPaths() as $registeredViewPath) {
            $pathName = str_replace(realpath($registeredViewPath) . '/', '', $pathName);
        }

        $viewName = str_replace_last('.blade.php','', $pathName);

        return $viewName;
    }

    public function compile(string $view): string
    {
        $crawler = new Crawler($view);

        foreach ($this->registeredComponents as $componentName => $classOrView) {
            $crawler
                ->filter($componentName)
                ->each(function (Crawler $subCrawler) use ($classOrView) {
                    $node = $subCrawler->getNode(0);

                    $node->parentNode->replaceChild(
                        $node->ownerDocument->createTextNode("@include('{$classOrView}')"), // TEMP: @include everything
                        $node
                    );
                });
        }

        return $crawler->html();
    }
}
