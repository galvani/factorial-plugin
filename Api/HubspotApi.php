<?php

namespace MauticPlugin\MauticFactorialBundle\Api;

use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\PluginBundle\Exception\ApiErrorException;

/**
 * @property HubspotIntegration $integration
 */
class HubspotApi extends CrmApi
{
    protected $requestSettings = [
        'encode_parameters' => 'json',
    ];

    protected function request($operation, $parameters = [], $method = 'GET', $object = 'contacts', $apiVersion = 'v2')
    {
        if ($apiVersion === 'v3') {
            $url = sprintf('%s/%s', $this->integration->getApiUrl(), $operation);
        } else {
            if ('oauth2' === $this->integration->getAuthenticationType()) {
                $url = sprintf('%s/%s/%s/', $this->integration->getApiUrl(), $object, $operation);
            } else {
                $url = sprintf('%s/%s/%s/?hapikey=%s', $this->integration->getApiUrl(), $object, $operation, $this->integration->getHubSpotApiKey());
            }
        }

        $request = $this->integration->makeRequest($url, $parameters, $method, $this->requestSettings);
        if (isset($request['status']) && 'error' == $request['status']) {
            $message = $request['message'];
            if (isset($request['validationResults'])) {
                $message .= " \n ".print_r($request['validationResults'], true);
            }
            if (isset($request['validationResults'][0]['error']) && 'PROPERTY_DOESNT_EXIST' == $request['validationResults'][0]['error']) {
                $this->createProperty($request['validationResults'][0]['name']);
                $this->request($operation, $parameters, $method, $object);
            } else {
                throw new ApiErrorException($message);
            }
        }

        if (isset($request['error']) && 401 == $request['error']['code']) {
            $response = json_decode($request['error']['message'] ?? null, true);

            if (isset($response)) {
                throw new ApiErrorException($response['message'], $request['error']['code']);
            } else {
                throw new ApiErrorException('401 Unauthorized - Error with Hubspot API', $request['error']['code']);
            }
        }

        if (isset($request['error'])) {
            throw new ApiErrorException($request['error']['message']);
        }

        return $request;
    }

    /**
     * @return mixed
     */
    public function getLeadFields($object = 'contacts')
    {
        if ('company' == $object) {
            $object = 'companies'; // hubspot company object name
        }

        return $this->request('v2/properties', [], 'GET', $object);
    }

    /**
     * Creates Hubspot lead.
     *
     * @return mixed
     */
    public function createLead(array $data, $lead, $updateLink = false)
    {
        /*
         * As Hubspot integration requires a valid email
         * If the email is not valid we don't proceed with the request
         */
        $email  = $data['email'];
        $result = [];
        // Check if the is a valid email
        MailHelper::validateEmail($email);
        // Format data for request
        $formattedLeadData = $this->integration->formatLeadDataForCreateOrUpdate($data, $lead, $updateLink);
        if ($formattedLeadData) {
            $result = $this->request('v1/contact/createOrUpdate/email/'.$email, $formattedLeadData, 'POST');
        }

        return $result;
    }

    /**
     * gets Hubspot contact.
     *
     * @return mixed
     */
    public function getContacts($params = [])
    {
        if ($params['fetchAll']) {
            unset($params['start'], $params['end']);
            return $this->request('crm/v3/objects/contacts', $params, 'GET', 'contacts', 'v3');   
        }
        
        $filter = [
            "filterGroups" => [
                [
                    "filters" => [
                        [
                            "propertyName" => "createdate",
                            "operator" => "GTE",
                            "value" => (new \DateTime($params['start']))->format("Y-m-d\TH:i:s.v\Z"),
                        ],
                        [
                            "propertyName" => "createdate",
                            "operator" => "LTE",
                            "value" => (new \DateTime($params['end']))->format("Y-m-d\TH:i:s.v\Z"),
                        ],
                        [
                            "propertyName" => "lastmodifieddate",
                            "operator" => "GTE",
                            "value" => (new \DateTime($params['start']))->format("Y-m-d\TH:i:s.v\Z"),
                        ],
                        [
                            "propertyName" => "lastmodifieddate",
                            "operator" => "LTE",
                            "value" => (new \DateTime($params['end']))->format("Y-m-d\TH:i:s.v\Z"),
                        ]
                    ]
                ]
            ],
            "sorts" => [
                [
                    "propertyName" => "createdate",
                    "direction" => "ASCENDING"
                ]
            ],
                "after" => $params['after'] ?? 0,
        ];

        $result = $this->request('crm/v3/objects/contacts/search', $filter, 'POST','contacts','v3');
        $ids = array_column($result['results'], 'id');

        $resultData =  $this->getContactsById($ids, $params['fields']);
        if (isset($result['paging'])) {
            $resultData['paging'] = $result['paging'];
        }
        
        return $resultData;
    }

    public function getContactsById(array $ids, $fields): array {
        $filter = [
            "properties" => $fields,
            "inputs" => array_map(function ($number) {
                return ["id" => (string)$number];
            }, $ids)
        ];

        
        return $this->request('crm/v3/objects/contacts/batch/read', $filter, 'POST','contacts','v3');
    }

    /**
     * gets Hubspot company.
     *
     * @return mixed
     */
    public function getCompanies($params, $id)
    {
        if ($id) {
            return $this->request('v2/companies/'.$id, $params, 'GET', 'companies');
        }

        return $this->request('v2/companies/recent/modified', $params, 'GET', 'companies');
    }

    /**
     * @param string $object
     *
     * @return mixed|string
     */
    public function createProperty($propertyName, $object = 'properties')
    {
        return $this->request('v1/contacts/properties', ['name' => $propertyName,  'groupName' => 'contactinformation', 'type' => 'string'], 'POST', $object);
    }
}
