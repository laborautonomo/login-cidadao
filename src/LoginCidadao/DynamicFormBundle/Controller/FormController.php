<?php
/**
 * This file is part of the login-cidadao project or it's bundles.
 *
 * (c) Guilherme Donato <guilhermednt on github>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LoginCidadao\DynamicFormBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use LoginCidadao\CoreBundle\Model\PersonInterface;
use LoginCidadao\DynamicFormBundle\Service\DynamicFormService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class FormController extends Controller
{
    /**
     * @Route("/client/{clientId}/dynamic-form", name="client_dynamic_form")
     * @Template("LoginCidadaoCoreBundle:DynamicForm:edit.html.twig")
     */
    public function indexAction(Request $request, $clientId)
    {
        $skipUrl = $request->get('redirect_url', $this->generateUrl('dynamic_form_skip', ['client_id' => $clientId]));
        $scope = explode(' ', $request->get('scope', null));

        /** @var DynamicFormService $formService */
        $formService = $this->get('dynamic_form.service');

        /** @var PersonInterface $person */
        $person = $this->getUser();

        $data = $formService->getDynamicFormData($person, $request, $request->get('scope', null));
        $type = 'LoginCidadao\DynamicFormBundle\Form\DynamicFormType';
        $form = $this->createForm($type, $data, ['dynamic_form_service' => $formService]);
        
        $formService->processForm($form, $request);

        return [
            'client' => $formService->getClient($clientId),
            'form' => $form->createView(),
            'scope' => $scope,
            'skipUrl' => $skipUrl,
        ];
    }
}
