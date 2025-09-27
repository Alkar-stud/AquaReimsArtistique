<?php

namespace app\Core;

use Exception;
use ReflectionClass;
use ReflectionException;

/**
 * Un conteneur d'injection de dépendances très simple.
 * Il peut instancier des classes et résoudre leurs dépendances de constructeur automatiquement.
 */
class Container
{
    /** @var array Stocke les instances déjà créées (singletons) */
    private array $instances = [];

    /**
     * Récupère ou crée une instance d'une classe.
     *
     * @template T
     * @param class-string<T> $class Le nom complet de la classe à instancier.
     * @return T
     * @throws ReflectionException|Exception
     */
    public function get(string $class)
    {
        // Si on a déjà créé cette instance, on la retourne directement.
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        // Si pas de constructeur, on peut juste faire un `new` simple.
        if ($constructor === null) {
            $instance = new $class();
        } else {
            $dependencies = [];
            $params = $constructor->getParameters();

            // Pour chaque paramètre du constructeur...
            foreach ($params as $param) {
                $dependencyClass = $param->getType()->getName();
                // ...on demande au conteneur de nous fournir cette dépendance (appel récursif).
                $dependencies[] = $this->get($dependencyClass);
            }

            // On instancie la classe avec toutes ses dépendances résolues.
            $instance = $reflection->newInstanceArgs($dependencies);
        }

        // On stocke l'instance pour les futurs appels et on la retourne.
        $this->instances[$class] = $instance;
        return $instance;
    }
}