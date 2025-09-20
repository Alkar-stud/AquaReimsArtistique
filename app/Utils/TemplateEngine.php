<?php
namespace app\Utils;

use RuntimeException;
use Throwable;

class TemplateEngine
{
    private string $baseDir = '';
    private array $phpBlocks = [];
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
        // Isoler les blocs PHP bruts pour les protéger des autres regex
        $template = $this->compilePhpBlocks($template);

        // Le reste du processus de compilation
        // ...

        // Enlever les commentaires (sans toucher aux échos bruts `{{! ... !}}`)
        $template = $this->compileComments($template);

        // Échos bruts (non échappés)
        $template = $this->compileRawEchos($template);

        // Échos échappés
        $template = $this->compileEchos($template);

        // Includes
        $template = $this->compileIncludes($template);

        // Structures de contrôle
        $template = $this->compileControlStructures($template);

        // Restaurer les blocs PHP bruts
        return str_replace(array_keys($this->phpBlocks), array_values($this->phpBlocks), $template);
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
        // Si le chemin commence par '/', on le considère relatif à la racine des templates.
        if (str_starts_with($path, '/')) {
            // On retire le premier '/' et on préfixe avec le chemin de base des templates.
            return __DIR__ . '/../views/templates' . $path;
        }
        // Sinon, c'est un chemin relatif au template courant.
        return rtrim($this->baseDir, '/\\') . DIRECTORY_SEPARATOR . $path;
    }

    private function compileControlStructures(string $template): string
    {
        $patterns = [
            '/\{%\s*if\s+(.+?)\s*%\}/'                  => '<?php if ($1): ?>',
            '/\{%\s*elseif\s+(.+?)\s*%\}/'              => '<?php elseif ($1): ?>',
            '/\{%\s*else\s*%\}/'                   => '<?php else: ?>',
            '/\{%\s*endif\s*%\}/'                  => '<?php endif; ?>',
            '/\{%\s*foreach\s+(.+?)\s+as\s+(.+?)\s*%\}/' => '<?php foreach ($1 as $2): ?>',
            '/\{%\s*endforeach\s*%\}/'             => '<?php endforeach; ?>',
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
            '/\{\{(.+?)\}\}/s',
            '<?= htmlspecialchars((string)($1), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>',
            $template
        );
    }

    private function compilePhpBlocks(string $template): string
    {
        return preg_replace_callback(
            '/\{%\s*php\s*%\}(.*?)\{%\s*endphp\s*%\}/s',
            function ($matches) {
                $phpCode = $matches[1];
                $placeholder = '___PHP_BLOCK_' . count($this->phpBlocks) . '___';
                // On encapsule le code dans les balises PHP pour l'eval() final
                $this->phpBlocks[$placeholder] = '<?php ' . $phpCode . ' ?>';
                return $placeholder;
            },
            $template
        );
    }
}
