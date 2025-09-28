<?php
namespace app\Core;

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
        // {% include 'file.tpl' with {'var': $value} %}
        return preg_replace_callback(
            '/\{%\s*include\s+[\'"](.+?)[\'"](?:\s+with\s+(.+?))?\s*%}/',
            function ($m) {
                $file = addslashes($m[1]); // Le chemin du fichier
                $context = $m[2] ?? '[]'; // Le contexte (ex: "{'event': $event}") ou un tableau vide

                // On fusionne le contexte local ($context) avec les données globales ($__data)
                // Le contexte local écrase les données globales en cas de conflit de nom.
                $data_to_pass = "array_merge(\$__data, $context)";

                return "<?php echo \$this->render(\$this->resolveInclude('$file'), $data_to_pass); ?>";
            },
            $template
        );
    }

    private function resolveInclude(string $path): string
    {
        // Chemin absolu (à partir de la racine des templates)
        if (str_starts_with($path, '/')) {
            return __DIR__ . '/../views/templates' . $path;
        }
        // Chemin relatif au template courant
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
            '/\{%\s*for\s+(.+?)\s+in\s+(.+?)\.\.(.+?)\s*%\}/' => '<?php for ($1 = $2; $1 <= $3; $1++): ?>',
            '/\{%\s*endfor\s*%\}/'                  => '<?php endfor; ?>',
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
        // Gère les échos avec ou sans filtres (ex: {{ $variable | date('Y-m-d') }})
        return preg_replace_callback(
            '/\{\{\s*(.+?)\s*}}/s',
            function ($matches) {
                $expression = $matches[1];
                $parts = explode('|', $expression);
                $variable = trim(array_shift($parts)); // La variable principale

                // Si pas de filtres, comportement par défaut
                if (empty($parts)) {
                    return "<?= htmlspecialchars((string)($variable), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>";
                }

                // Appliquer les filtres en chaîne
                $isComplexExpression = false;
                $output = $variable;
                foreach ($parts as $filter) {
                    $output = $this->applyFilter($output, trim($filter));
                    $isComplexExpression = true; // Un filtre a été appliqué, c'est une expression
                }

                // Si c'est une expression complexe, on ne l'entoure pas de parenthèses supplémentaires.
                $finalOutput = $isComplexExpression ? $output : "($output)";
                return "<?= htmlspecialchars((string)$finalOutput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>";
            },
            $template
        );
    }

    private function applyFilter(string $variable, string $filter): string
    {
        // Recherche du nom du filtre et de ses arguments (ex: date('Y-m-d H:i:s'))
        if (preg_match('/^(\w+)\s*\((.*)\)$/', $filter, $matches)) {
            $filterName = $matches[1];
            $filterArgs = $matches[2];

            if ($filterName === 'date') {
                $format = $filterArgs ?: "'Y-m-d H:i:s'"; // Format par défaut
                // On récupère le fuseau horaire défini pour l'application
                $timezone = date_default_timezone_get();
                // Génère un code PHP qui gère les valeurs nulles ou vides
                return "(!empty($variable) ? "
                    . "(new \\DateTime($variable))->setTimezone(new \\DateTimeZone('$timezone'))->format($format) "
                    . ": '')";
            }
        }

        // Si le filtre n'est pas reconnu ou n'a pas de parenthèses, on le retourne tel quel pour l'instant
        return $variable;
    }

    private function compilePhpBlocks(string $template): string
    {
        return preg_replace_callback(
            '/\{%\s*php\s*%}(.*?)\{%\s*endphp\s*%}/s',
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
