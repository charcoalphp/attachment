<?php

namespace Charcoal\Attachment\Object;

// From Pimple
use Pimple\Container as ServiceContainer;

// From 'charcoal-config'
use Charcoal\Config\ConfigurableInterface;

// From 'charcoal-attachment'
use Charcoal\Attachment\Interfaces\AttachmentAwareInterface;
use Charcoal\Attachment\Interfaces\AttachmentContainerInterface;
use Charcoal\Attachment\Traits\AttachmentAwareTrait;
use Charcoal\Attachment\Traits\AttachmentContainerTrait;
use Charcoal\Attachment\Traits\ConfigurableAttachmentsTrait;

/**
 * Gallery Attachment Type
 *
 * This type allows for nesting of additional attachment types.
 */
class Container extends Attachment implements
    AttachmentAwareInterface,
    AttachmentContainerInterface,
    ConfigurableInterface
{
    use AttachmentAwareTrait {
        AttachmentAwareTrait::attachments as getAttachments;
    }
    use AttachmentContainerTrait;
    use ConfigurableAttachmentsTrait;

    /**
     * Inject dependencies from a DI Container.
     *
     * @param  ServiceContainer $container A dependencies container instance.
     * @return void
     */
    protected function setDependencies(ServiceContainer $container)
    {
        parent::setDependencies($container);

        if (isset($container['attachments/config'])) {
            $this->setConfig($container['attachments/config']);
        } elseif (isset($container['config']['attachments'])) {
            $this->setConfig($container['config']['attachments']);
        }
    }

    /**
     * Retrieve the objects associated to the current object.
     *
     * @param  mixed ...$args Filter the attachments;
     *     options accepted by {@see AttachmentAwareTrait::attachments()}.
     * @return Collection|Attachment[]
     */
    public function attachments(...$args)
    {
        $attachables = $this->attachableObjects();
        $attachments = call_user_func_array([ $this, 'getAttachments' ], $args);

        foreach ($attachments as $attachment) {
            if (isset($attachables[$attachment->objType()])) {
                $attachment->attachmentType = $attachables[$attachment->objType()];
            } else {
                $attachment->attachmentType = [];
            }
        }

        return $attachments;
    }
}
