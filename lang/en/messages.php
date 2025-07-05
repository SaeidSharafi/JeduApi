<?php

declare(strict_types=1);

return [

    'created'                => ':model created successfully.',
    'updated'                => ':model updated successfully.',
    'deleted'                => ':model deleted successfully.',
    'not_found'              => ':model not found.',
    'success'                => 'Operation successful.',
    'error'                  => 'An error occurred while performing the operation.',
    'unauthorized'           => 'You are not authorized for this action.',
    'forbidden'              => 'You do not have permission to access this resource.',
    'validation_error'       => 'Validation error.',
    'validation_errors'      => 'Validation error: :errors',
    'unauthenticated'        => 'You are not authenticated or your session has expired.',
    'server_error'           => 'An internal server error occurred. Please try again later.',
    'method_not_allowed'     => 'The method is not allowed for this resource.',
    'resource_not_found'     => 'The requested resource was not found.',
    'action_not_allowed'     => 'This action is not allowed for this resource.',
    'action_not_permitted'   => 'You do not have permission to perform this action.',
    'action_not_supported'   => 'This action is not supported in this version.',
    'action_not_implemented' => 'This action has not been implemented yet.',
    'action_successful'      => 'Action successful.',
    'action_failed'          => 'The action failed. Please try again.',
    'action_in_progress'     => 'Action is in progress. Please wait.',
    'action_completed'       => 'Action completed successfully.',
    'action_cancelled'       => 'Action cancelled.',
    'action_pending'         => 'Action is pending. Please wait.',
    'media_uploaded'         => 'Media uploaded successfully.',
    'media_deleted'          => 'Media deleted successfully.',
    'media_not_found'        => 'The requested media was not found.',
    'file_uploaded'          => 'File uploaded successfully.',
    'file_deleted'           => 'File deleted successfully.',
    'file_not_found'         => 'The requested file was not found.',
    'models'                 => [
        'seminar'       => 'Seminar',
        'staff'         => 'Staff',
        'user'          => 'User',
        'category'      => 'Category',
        'digital_asset' => 'Digital Asset',
        'course'        => 'Course',
        'teacher'       => 'Teacher',
        'term'          => 'Term',
    ],

    'errors' => [
        'model_has_relationship_data'                       => 'The selected record has related data (:related_model) and cannot be deleted.',
        'model_has_relationship_data_without_related_model' => 'The selected record has related data and cannot be deleted.',
    ],
    'order' => [
        'items_already_purchased' => 'You have already purchased the following items: :products.',
        'item_already_purchased'  => 'You have already purchased this item.',
        'prepayment_not_available' => 'Pre-payment is not available for product :product.',
        'payment_already_pending'  => 'Order :order_id has a pending payment.',
        'order_amount_to_pay_is_zero' => 'The amount to pay for order :order_id is zero and does not require payment.',
    ],
];
