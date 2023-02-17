<?php

namespace AuthorisationCheckBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CheckCommand extends Command
{
    protected static $defaultName = 'authorisation-check:check';

    public function __construct(
        private readonly RouterInterface $router,
        private readonly HttpClientInterface $client
    ) {
        parent::__construct($this::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Checks if the authorisation is correct')
            ->setHelp('This command allows you to check if the authorisation is correct')
            ->addArgument('host', InputArgument::OPTIONAL, 'localhost:8000', "localhost:8000")
            ->addArgument('scheme', InputArgument::OPTIONAL, 'url', "http");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $context = $this->router->getContext();
        $context->setHost($input->getArgument('host'));
        $context->setScheme($input->getArgument('scheme'));
        $context->setBaseUrl('');

        $allRoutes = $this->router->getRouteCollection()->all();

        $output->writeln(sprintf('Found %d routes', count($allRoutes)));

        $output->writeln('Checking routes...');

        foreach ($allRoutes as $routeName => $route) {
            //if route start with _, skip (it's a symfony route)
            if (str_starts_with($routeName, '_')) {
                continue;
            }

            $output->writeln(sprintf('Trying route %s', $routeName));

            //get name of all parameters in route
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $route->getPath(), $matches);
            $parameters = $matches[1];

            $parametersValues = [];

            foreach ($parameters as $parameter) {
                if ($route->hasDefault($parameter)) {
                    $parametersValues[$parameter] = $route->getDefault($parameter);
                } else {
                    $parametersValues[$parameter] = 1;
                }
            }

            try {

                $url = $this->router->generate($routeName, $parametersValues, UrlGeneratorInterface::ABSOLUTE_URL);

                $response = $this->client->request('GET', $url, [
                    'max_redirects' => 1,
                ]);

                if ($response->getInfo('url') === $url && in_array($response->getStatusCode(), [200, 500], true)) {
                    $output->writeln(sprintf('<fg=red>Route %s is accessible without authorisation, statusCode : %s, url : %s</>', $routeName, $response->getStatusCode(), $response->getInfo('url')));
                }
            } catch (\Throwable $th) {
                $output->writeln($th->getMessage());
            }


        }

        $output->writeln('Done!');


        return 0;
    }
}