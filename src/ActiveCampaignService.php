<?php

namespace CodeByKyle\ActiveCampaign;


use ActiveCampaign;

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
     * IntercomService constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->ac = new ActiveCampaign($config['api_url'], $config['api_key']);
        $this->ac->track_actid = $config['account_id'];
        $this->ac->track_key   = $config['event_key'];
    }

    /**
     * Get the ActiveCampaign reference
     * @return ActiveCampaign
     */
    public function getActiveCampaign() {
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
     * Create a contact with the specified email. Optional additional data via the data param
     * see: http://www.activecampaign.com/api/example.php?call=contact_add
     * @param $email
     * @param array $data
     * @return bool
     */
    public function createContact($email, array $data=[]) {
        $result = $this->ac->api("contact/add", array_merge([
            'email' => $email
        ], $data));

        return (bool)$result->success;
    }


    /**
     * Add one or multiple tags to a contact
     * @param $id
     * @param array $tags
     * @return bool
     */
    public function addTagToContact($id, array $tags=[])
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
    public function removeTagsFromContact($id, array $tags=[])
    {
        $result = $this->ac->api('contact/tag_remove', [
            'id' => $id,
            'tags' => $tags
        ]);

        return (bool)$result->success;
    }


    public function contactList($ids='ALL') {
        $result = $this->ac->api('contact/list', [
            'ids' => $ids
        ]);

        return $result;
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
     * @param array $data
     *
     * @return bool
     */
    public function contactSync(array $data)
    {
        $result = $this->ac->api("contact/sync", $data);
        return (bool)$result->success;
    }

    public function version($versionNumber)
    {
        return $this->ac->version($versionNumber);
    }

    /**
     * @return string
     */
    public function trackEventList()
    {
        $this->version(self::API_VERSION_TWO);

        return $this->ac->api('tracking/event/list');
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
     * @param array     $new_lists_ids
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
}
