<?php
namespace app\Utils;

use RuntimeException;
use Throwable;

class TemplateEngine
{
    public function render(string $templatePath, array $data = []): string
    {
        if (!file_exists($templatePath)) {
            throw new RuntimeException("Le template '$templatePath' n'existe pas.");
        }
        $template = file_get_contents($templatePath);

        // Gestion des inclusions {% include '...' %}
        $template = $this->compileIncludes($template, dirname($templatePath));

        // Remplacement des balises de logique (if, foreach, etc.)
        $template = $this->compileControlStructures($template);

        // Remplacement des variables et expressions
        $template = $this->compileEchos($template);

        // Évaluation du template compilé dans un scope isolé
        extract($data);
        ob_start();
        try {
            eval('?>' . $template);
        } catch (Throwable $e) {
            ob_end_clean();
            throw new RuntimeException("Erreur lors du rendu du template '$templatePath': " . $e->getMessage(), 0, $e);
        }
        return ob_get_clean() ?: '';
    }

    private function compileIncludes(string $template, string $templateDir): string
    {
        // Utilise une fonction de rappel pour gérer les inclusions
        return preg_replace_callback('/\{% include [\'"](.+?)[\'"] %}/', function ($matches) use ($templateDir) {
            // Le chemin du template à inclure, ex : '_menu.tpl'
            $includePath = $matches[1];
            // On construit le chemin absolu vers le template à inclure
            // On part du dossier du template parent pour résoudre le chemin
            $fullPath = realpath($templateDir . '/' . $includePath);

            // On ne passe que les variables du tableau $data original pour éviter une récursion infinie
            // avec les variables internes de la méthode render().
            // On utilise addslashes pour échapper les apostrophes dans le chemin du fichier.
            return '<?= $this->render(\'' . addslashes($fullPath) . '\', $data) ?>';
        }, $template);
    }

    private function compileControlStructures(string $template): string
    {
        $patterns = [
            '/\{% if (.+?) %\}/' => '<?php if ($1): ?>',
            '/\{% elseif (.+?) %\}/' => '<?php elseif ($1): ?>',
            '/\{% else %\}/' => '<?php else: ?>',
            '/\{% endif %\}/' => '<?php endif; ?>',
            '/\{% foreach (.+?) as (.+?) %\}/' => '<?php foreach ($1 as $2): ?>',
            '/\{% endforeach %\}/' => '<?php endforeach; ?>',
            '/\{% for (.+?) %\}/' => '<?php for ($1): ?>',
            '/\{% endfor %\}/' => '<?php endfor; ?>'
        ];
        return preg_replace(array_keys($patterns), array_values($patterns), $template);
    }

    private function compileEchos(string $template): string
    {
        // {{! variable !}} pour du HTML brut (non échappé)
        $template = preg_replace_callback('/\{\{! ([^}]+) !}}/', function ($matches) {
            return '<?= ' . $matches[1] . ' ?>';
        }, $template);
        // {{ variable }} pour des données échappées
        return preg_replace_callback('/\{\{ ([^}]+) }}/', function ($matches) {
            return '<?= htmlspecialchars(' . $matches[1] . ', ENT_QUOTES, \'UTF-8\') ?>';
        }, $template);
    }
}
