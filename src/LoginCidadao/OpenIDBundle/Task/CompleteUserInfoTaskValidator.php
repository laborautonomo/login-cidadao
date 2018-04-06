<?php
/**
 * This file is part of the login-cidadao project or it's bundles.
 *
 * (c) Guilherme Donato <guilhermednt on github>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LoginCidadao\OpenIDBundle\Task;

use FOS\OAuthServerBundle\Event\OAuthEvent;
use LoginCidadao\CoreBundle\Entity\City;
use LoginCidadao\CoreBundle\Entity\Country;
use LoginCidadao\CoreBundle\Entity\State;
use LoginCidadao\CoreBundle\Model\PersonInterface;
use LoginCidadao\OAuthBundle\Model\ClientInterface;
use LoginCidadao\ValidationBundle\Validator\Constraints\CPFValidator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CompleteUserInfoTaskValidator
{
    /** @var EventDispatcherInterface */
    private $dispatcher;

    /**
     * Allow the CompleteUserInfoTask to be skipped if the user has already authorized the RP and the RP didn't ask
     * for the Task explicitly.
     * @var bool
     */
    private $skipIfAuthorized;

    /**
     * CompleteUserInfoTaskValidator constructor.
     * @param EventDispatcherInterface $dispatcher
     * @param bool $skipIfAuthorized
     */
    public function __construct(EventDispatcherInterface $dispatcher, $skipIfAuthorized)
    {
        $this->dispatcher = $dispatcher;
        $this->skipIfAuthorized = $skipIfAuthorized;
    }

    /**
     * Check if prompt=consent was requested (also checks the Nonce)
     *
     * @param Request $request
     * @return bool
     */
    public function shouldPromptConsent(Request $request)
    {
        return $request->get('prompt', null) === 'consent' && $this->isNonceValid($request);
    }

    /**
     * Checks if the Client/RP already has the user's authorization.
     * @param PersonInterface $person
     * @param ClientInterface $client
     * @return bool
     */
    public function isClientAuthorized(PersonInterface $person, ClientInterface $client)
    {
        /** @var OAuthEvent $event */
        $event = $this->dispatcher->dispatch(
            OAuthEvent::PRE_AUTHORIZATION_PROCESS,
            new OAuthEvent($person, $client)
        );

        return $event->isAuthorizedClient();
    }

    /**
     * Check if the Request's route is valid for this Task
     * @param Request $request
     * @return bool
     */
    public function isRouteValid(Request $request)
    {
        $route = $request->get('_route');
        $scopes = $request->get('scope', false);

        return $route === '_authorize_validate' && false !== $scopes;
    }

    /**
     * Get the CompleteUserInfoTask for the $user's missing data
     *
     * @param PersonInterface $user
     * @param ClientInterface $client
     * @param Request $request
     * @return CompleteUserInfoTask|null
     */
    public function getCompleteUserInfoTask(PersonInterface $user, ClientInterface $client, Request $request)
    {
        if (!$this->isRouteValid($request) || $this->canSkipTask($request, $user, $client)) {
            return null;
        }

        $emptyClaims = [];
        $scopes = explode(' ', $request->get('scope', false));
        foreach ($scopes as $scope) {
            if (false === $this->checkScope($user, $scope)) {
                $emptyClaims[] = $scope;
            }
        }

        if (empty($emptyClaims) > 0) {
            return null;
        }

        return new CompleteUserInfoTask($client->getPublicId(), $emptyClaims, $request->get('nonce'));
    }

    /**
     * @param Request $request
     * @return bool
     */
    private function isNonceValid(Request $request)
    {
        $nonce = $request->get('nonce', null);

        // TODO: check is nonce was already used

        return $nonce !== null;
    }

    /**
     * Check if scope's data is missing from $user
     *
     * @param PersonInterface $user
     * @param string $scope
     * @return bool
     */
    private function checkScope(PersonInterface $user, $scope)
    {
        // 'id_cards', 'addresses'
        switch ($scope) {
            case 'name':
            case 'full_name':
            case 'surname':
                $value = $user->getFullName();

                return $value && strlen($value) > 0 && strlen($user->getSurname()) > 0;
                break;
            case 'mobile':
            case 'phone_number':
                $value = $user->getMobile();
                break;
            case 'country':
                return $user->getCountry() instanceof Country;
            case 'state':
                return $user->getState() instanceof State;
            case 'city':
                return $user->getCity() instanceof City;
            case 'birthdate':
                return $user->getBirthdate() instanceof \DateTime;
            case 'email':
            case 'email_verified':
                return $user->getEmailConfirmedAt() instanceof \DateTime;
            case 'cpf':
                $cpf = $user->getCpf();

                return $cpf && CPFValidator::isCPFValid($cpf);
            default:
                return true;
        }

        return $value && strlen($value) > 0;
    }

    /**
     * Check if this Task can be skipped based on an existing Authorization
     *
     * @param Request $request
     * @param PersonInterface $user
     * @param ClientInterface $client
     * @return bool
     */
    private function canSkipTask(Request $request, PersonInterface $user, ClientInterface $client)
    {
        // Can this Task be skipped if the RP is already Authorized?
        if ($this->skipIfAuthorized) {

            // To force this task's execution, the RP MUST send prompt=consent and a nonce value.
            $shouldPromptConsent = $this->shouldPromptConsent($request);
            $isAuthorized = $this->isClientAuthorized($user, $client);

            // Skip if the RP is authorized and the Task wasn't explicitly requested
            if ($isAuthorized && !$shouldPromptConsent) {
                return true;
            }
        }

        return false;
    }
}
