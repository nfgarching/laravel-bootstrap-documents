<?php

namespace Nfgarching\Documents;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Nfgarching\Core\Modals\ModalRegistry;
use Nfgarching\Core\Navigation\NavbarNavigation;
use Nfgarching\Documents\Console\CheckCommand;
use Nfgarching\Documents\Console\InstallCommand;
use Nfgarching\Documents\Livewire\DocumentComposer;
use Nfgarching\Documents\Livewire\LayoutEditor;
use Nfgarching\Documents\Livewire\Library;
use Nfgarching\Documents\Livewire\TemplateEditor;
use Nfgarching\Documents\Pdf\Gotenberg;
use Nfgarching\Documents\Rendering\DataResolver;
use Nfgarching\Documents\Rendering\DocxRenderer;
use Nfgarching\Documents\Rendering\HtmlRenderer;

class DocumentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/documents.php', 'nfdocuments');

        $this->app->scoped(DataResolver::class);
        $this->app->singleton(HtmlRenderer::class);
        $this->app->singleton(DocxRenderer::class);
        $this->app->scoped(DocumentManager::class);
        $this->app->bind(Gotenberg::class, fn () => Gotenberg::fromConfig());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nfdocuments');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Livewire::component('nf-documents', Library::class);
        Livewire::component('nf-documents-layout', LayoutEditor::class);
        Livewire::component('nf-documents-template', TemplateEditor::class);
        Livewire::component('nf-document-composer', DocumentComposer::class);

        // Navbar-Eintrag und Modal nur, wenn das core-Package die Registries bereitstellt
        if (class_exists(NavbarNavigation::class)) {
            $this->app->make(NavbarNavigation::class)->item('nfdocuments::parts.navbar', order: 71);
            $this->app->make(ModalRegistry::class)->modal('nfdocuments::parts.modals', order: 84);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, CheckCommand::class]);

            $this->publishes([
                __DIR__.'/../config/documents.php' => config_path('nfdocuments.php'),
            ], 'nfdocuments-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/nfdocuments'),
            ], 'nfdocuments-views');

            $this->publishes([
                __DIR__.'/../stubs/AkteDataProvider.php' => app_path('Documents/AkteDataProvider.php'),
            ], 'nfdocuments-stubs');
        }
    }
}
