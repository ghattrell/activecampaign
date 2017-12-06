<?php

namespace CodeByKyle\ActiveCampaign;


use ActiveCampaign;
use function Aws\map;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Class ActiveCampaignService
 *
 * @package CodeByKyle\ActiveCampaign
 */
class ActiveCampaignService
{
    const API_VERSION_TWO = 2;

    /**
     * @var \ActiveCampaign
     */
    public $ac;

    /**
     * @var "The config passed into the service
     */
    protected $config;

    /**
     * @var "Guzzle client for endpoints having an issue"
     */
    protected $guzzle;


    /**
     * IntercomService constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        $this->ac = new ActiveCampaign($config['api_url'], $config['api_key']);
        $this->ac->track_actid = $config['account_id'];
        $this->ac->track_key = $config['event_key'];

        $this->guzzle = new GuzzleClient();
    }

    /**
     * Get the ActiveCampaign reference
     * @return ActiveCampaign
     */
    public function getActiveCampaign()
    {
        return $this->ac;
    }

    /**
     * @return mixed
     */
    public function accountView()
    {
        return $this->ac->api("account/view");
    }


    /**
     * Get a list of contacts from Active Campaign. Optional filters array.
     * @see: https://www.activecampaign.com/api/example.php?call=contact_list
     * @param array $filters
     * @return array $data
     */
    public function listContacts(array $filters = [])
    {
        // The version switching in the client library is not ideal. The v2 endpoint has a bug where users are not being returned.
        // This has a current open ticket with Active Campaign. Instead we will hardcode a request to the api endpoint provided
        // in the API sandbox to get a list of current contacts.
        // Note: With the current API endpoint for listing contacts, contacts must be a part of a list in AC or they will NOT be returned

        $url = $this->config['api_url'];
        $key = $this->config['api_key'];

        $joinedParams = array_merge([
            'api_key' => $key,
            'api_action' => 'contact_list',
            'api_output' => 'json',
            'ids' => 'all',
            'sort' => 'id',
            'sort_direction' => 'ASC',
            'page' => 1
        ], $filters);


        $responseStream = $this->guzzle->get($url . '/admin/api.php', [
            'query' => $joinedParams
        ]);

        if ($responseStream->getStatusCode() == 200) {
            $response = $responseStream->getBody()->getContents();
            $jsonResponse = json_decode($response, true);

            $arrayOfItems = collect($jsonResponse)
                ->keys()
                ->filter(function ($item) {
                    return is_numeric($item);
                })->map(function ($item) use ($jsonResponse) {
                    return (object)$jsonResponse[$item];
                });

            return $arrayOfItems;
        }

        return null;
    }


    public function editContact($id, array $data=[], $saveLists = true) {
        // save the lists the user is assigned to
        $dataProperties = [
            'id' => $id,
            'overwrite' => 0
        ];

        if ($saveLists) {
            $contact = $this->contactView($id);

            foreach ($contact->lists as $list) {
                $dataProperties["p[{$list->listid}]"] = $list->listid;
            }
        }


        $result = $this->ac->api('contact/edit', array_merge($dataProperties, $data));

        return (bool)$result->success;
    }

    /**
     * Create a contact with the specified email. If a contact already exists with that ID,
     * return the existing user instead
     * Optional additional data via the data param
     * see: http://www.activecampaign.com/api/example.php?call=contact_add
     * @param $email
     * @param array $data
     * @return int "The ID of the created or discovered contact"
     */
    public function createContact($email, array $data = [])
    {
        $result = $this->ac->api("contact/add", array_merge([
            'email' => $email
        ], $data));

        if ((bool)$result->success) {
            if (property_exists($result, 'subscriber_id')) {
                return $result->subscriber_id;
            }
        } else {
            // The user already exists, return that ID instead
            if ($result->result_code == 0 && property_exists($result, "0")) {
                return (int)$result->{'0'}->id;
            }
        }

        return null;
    }


    /**
     * Add one or multiple tags to a contact
     * @param id
     * @param array $tags
     * @return bool
     */
    public function addTagToContact($id, array $tags = [])
    {
        $result = $this->ac->api('contact/tag_add', [
            'id' => $id,
            'tags' => $tags
        ]);

        return (bool)$result->success;
    }


    /**
     * Remove one or many tags from a contact
     * @param $id
     * @param array $tags
     * @return bool
     */
    public function removeTagsFromContact($id, array $tags = [])
    {
        $result = $this->ac->api('contact/tag_remove', [
            'id' => $id,
            'tags' => $tags
        ]);

        return (bool)$result->success;
    }


    /**
     * @param $id
     *
     * @return \stdClass|null
     */
    public function contactView($id)
    {
        return $this->returnNullIfNotFound($this->ac->api("contact/view?id={$id}"));
    }

    /**
     * @param $response
     *
     * @return \stdClass|null
     */
    protected function returnNullIfNotFound($response)
    {
        if (empty($response->success)) {
            return null;
        }

        return $response;
    }

    /**
     * @param $email
     *
     * @return \stdClass|null
     */
    public function contactViewByEmail($email)
    {
        return $this->returnNullIfNotFound($this->ac->api("contact/view?email={$email}"));
    }

    /**
     * @param $hash
     *
     * @return \stdClass|null
     */
    public function contactViewByHash($hash)
    {
        return $this->returnNullIfNotFound($this->ac->api("contact/view?hash={$hash}"));
    }

    /**
     * @return string
     */
    public function trackEventList()
    {
        $this->version(self::API_VERSION_TWO);

        return $this->ac->api('tracking/event/list');
    }

    public function version($versionNumber)
    {
        return $this->ac->version($versionNumber);
    }

    /**
     * @param array $postData
     * @return string
     */
    public function logEvent(array $postData)
    {
        $this->ac->track_email = $postData['email'];

        return $this->ac->api('tracking/log', $postData);
    }

    /**
     * @param \stdClass $contact
     * @param array $new_lists_ids
     *
     * @return bool
     */
    public function contactChangeLists(\stdClass $contact, array $new_lists_ids)
    {
        $old_lists = $new_lists = [];

        foreach ($contact->lists as $list) {
            $old_lists["p[{$list->listid}]"] = $list->listid;
            $old_lists["status[{$list->listid}]"] = 2; // 2 = unsubscribed
        }

        foreach ($new_lists_ids as $new_lists_id) {
            $new_lists["p[{$new_lists_id}]"] = $new_lists_id;
            $new_lists["status[{$new_lists_id}]"] = 1; // 1 = active
        }

        $lists = array_merge($old_lists, $new_lists);
        $lists['email'] = $contact->email;

        return $this->contactSync($lists);
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function contactSync(array $data)
    {
        $result = $this->ac->api("contact/sync", $data);
        return (bool)$result->success;
    }
}
