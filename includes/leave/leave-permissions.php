<?php
if (!defined('ABSPATH')) exit;

/**
 * ECCO Leave Permissions
 *
 * Determines whether the currently logged-in user is allowed to
 * approve or reject a given leave request.
 *
 * A user can approve a request when ANY of the following is true:
 *  1. They are a WordPress administrator (manage_options).
 *  2. Their email matches the manager_email stored on the request.
 *  3. They are the requester AND their account is flagged as a
 *     self-manager (user meta `ecco_is_self_manager` === '1').
 */
function ecco_current_user_can_approve_leave( $request ) {

    if ( ! is_user_logged_in() ) {
        return false;
    }

    /* Already actioned — nobody can re-approve/reject */
    if ( in_array( $request->status, [ 'approved', 'rejected' ], true ) ) {
        return false;
    }

    /* 1. Site administrators can approve everything */
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }

    $current_user = wp_get_current_user();

    /* 2. User whose email is recorded as the manager for this request */
    if ( ! empty( $request->manager_email ) ) {
        if ( strtolower( $current_user->user_email ) === strtolower( $request->manager_email ) ) {
            return true;
        }
    }

    /* 3. Self-manager: the requester approves their own requests */
    $is_self_manager = get_user_meta( $request->user_id, 'ecco_is_self_manager', true );

    if ( $is_self_manager === '1' && (int) $current_user->ID === (int) $request->user_id ) {
        return true;
    }

    return false;
}


/**
 * Helper: is a given WordPress user flagged as a self-manager?
 */
function ecco_user_is_self_manager( $user_id ) {
    return get_user_meta( (int) $user_id, 'ecco_is_self_manager', true ) === '1';
}
