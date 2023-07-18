<?php
namespace App\Traits;

use App\Models\Collections;
use Illuminate\Auth\Access\AuthorizationException;

trait CollectionAuthorizeRequestTrait
{
    protected $failedCollectionName;

    public function authorize()
    {
        if($this->mode && $this->mode == 'delete'){
            return $this->authorizeForDelete();
        }

        return $this->authorizeCollectionId($this->collection_id);
    }

    public function authorizeCollectionId($collectionId = null)
    {
        if(!$collectionId) return true;

        $collection = Collections::find($collectionId);
        if(!$collection) return true;

        if($collection->status === Collections::STATUS_VERIFIED){
            $this->failedCollectionName = $collection->name;
            return false;
        }

        return true;
    }

    public function authorizeForDelete()
    {
        if(!$this->id || !is_array($this->id) || empty($this->id)){
            return true;
        }

        foreach($this->id as $collectionId){
            if(!$this->authorizeCollectionId($collectionId)){
                return false;
            }
        }

        return true;
    }

    public function failedAuthorization()
    {
        throw new AuthorizationException("Collection '$this->failedCollectionName' is verified, you cannott modify/delete it.");
    }
}
