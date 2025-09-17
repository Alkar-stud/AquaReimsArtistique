<?php
namespace app\Utils;

use RuntimeException;
use Throwable;

class TemplateEngine
{
    private string $baseDir = '';
    private array $baseDirStack = [];

    public function render(string $templatePath, array $data = []): string
    {
        if (!is_file($templatePath)) {
            throw new RuntimeException("Le template '$templatePath' n'existe pas.");
        }

        $this->pushBaseDir(dirname($templatePath));

        $template = file_get_contents($templatePath);
        $template = $this->compile($template);

        // Variables disponibles dans le template
        $__data = $data;                 // paquet de variables à propager aux includes
        extract($__data, EXTR_OVERWRITE);

        // Valeurs par défaut sûres
        if (!isset($load_ckeditor)) {
            $load_ckeditor = false;
        }
        if (!isset($is_gestion_page)) {
            $is_gestion_page = false;
        }


        ob_start();
        try {
            eval('?>' . $template);
        } catch (Throwable $e) {
            ob_end_clean();
            $this->popBaseDir();
            throw new RuntimeException("Erreur lors du rendu du template '$templatePath': " . $e->getMessage(), 0, $e);
        }
        $out = ob_get_clean() ?: '';

        $this->popBaseDir();
        return $out;
    }

    private function pushBaseDir(string $dir): void
    {
        $this->baseDirStack[] = $this->baseDir;
        $this->baseDir = $dir;
    }

    private function popBaseDir(): void
    {
        $this->baseDir = array_pop($this->baseDirStack) ?? '';
    }

    private function compile(string $template): string
    {
        // Enlever les commentaires (sans toucher aux échos bruts `{{! ... !}}`)
        $template = $this->compileComments($template);

        // Includes
        $template = $this->compileIncludes($template);

        // Structures de contrôle
        $template = $this->compileControlStructures($template);

        // Échos bruts (non échappés)
        $template = $this->compileRawEchos($template);

        // Échos échappés
        return $this->compileEchos($template);
    }

    private function compileComments(string $template): string
    {
        // Handlebars: {{!-- ... --}}
        // Ne pas supprimer les formes `{{! ... !}}` (échos bruts) ni `{{! ... }}` pour éviter conflits
        return preg_replace('/\{\{!--.*?--}}/s', '', $template);
    }

    private function compileIncludes(string $template): string
    {
        // {% include 'file.tpl' %}
        return preg_replace_callback(
            '/\{%\s*include\s+[\'"](.+?)[\'"]\s*%}/',
            function ($m) {
                $file = addslashes($m[1]);
                // Passer uniquement $__data pour éviter l'inflation de scope
                return "<?php echo \$this->render(\$this->resolveInclude('$file'), \$__data); ?>";
            },
            $template
        );
    }

    private function resolveInclude(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }
        return rtrim($this->baseDir, '/\\') . DIRECTORY_SEPARATOR . $path;
    }

    private function compileControlStructures(string $template): string
    {
        $patterns = [
            '/\{% if (.+?) %\}/'               => '<?php if ($1): ?>',
            '/\{% elseif (.+?) %\}/'           => '<?php elseif ($1): ?>',
            '/\{% else %\}/'                   => '<?php else: ?>',
            '/\{% endif %\}/'                  => '<?php endif; ?>',
            '/\{% foreach (.+?) as (.+?) %\}/' => '<?php foreach ($1 as $2): ?>',
            '/\{% endforeach %\}/'             => '<?php endforeach; ?>',
        ];
        return preg_replace(array_keys($patterns), array_values($patterns), $template);
    }

    private function compileRawEchos(string $template): string
    {
        // {{! ... !}}
        return preg_replace('/\{\{!\s*(.+?)\s*!}}/s', '<?= $1 ?>', $template);
    }

    private function compileEchos(string $template): string
    {
        return preg_replace(
            '/\{\{\s*(.+?)\s*}}/s',
            '<?= htmlspecialchars((string)($1), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>',
            $template
        );
    }
}
