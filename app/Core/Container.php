<?php

namespace app\Core;

use Exception;
use ReflectionClass;
use ReflectionException;

/**
 * Un conteneur d'injection de dépendances.
 * Il peut instancier des classes et résoudre leurs dépendances de constructeur.
 */
class Container
{
    /** @var array Stocke les instances déjà créées (singletons) */
    private array $instances = [];

    /** @var array Stocke les règles de construction pour les interfaces ou classes complexes */
    private array $bindings = [];

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
        // Si une règle de construction spécifique existe pour cette classe/interface, on l'utilise.
        if (isset($this->bindings[$class])) {
            // Si on ne l'a pas encore construite, on appelle la fonction de construction.
            if (!isset($this->instances[$class])) {
                $factory = $this->bindings[$class];
                // On passe le conteneur lui-même à la factory, au cas où elle aurait besoin de résoudre d'autres dépendances.
                $this->instances[$class] = $factory($this);
            }
            // On retourne l'instance (maintenant en cache).
            return $this->instances[$class];
        }

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
            // On vérifie si la classe peut être instanciée (pas une interface ou une classe abstraite)
            if (!$reflection->isInstantiable()) {
                throw new Exception("Cannot instantiate non-instantiable class or interface: {$class}");
            }

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

    /**
     * Définit une règle de construction (une "factory") pour une classe ou une interface.
     *
     * @param string $abstract Le nom de la classe ou de l'interface à configurer.
     * @param callable $factory Une fonction qui prend le conteneur en argument et retourne l'instance construite.
     * @return void
     */
    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }
}