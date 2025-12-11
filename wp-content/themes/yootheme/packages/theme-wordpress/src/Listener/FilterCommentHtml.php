<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class FilterCommentHtml
{
    /**
     * Remove "novalidate" attribute from comment form.
     */
    public static function form()
    {
        if (is_singular() && comments_open()) {
            echo '<script>if (window.commentform) {commentform.removeAttribute("novalidate")}</script>';
        }
    }

    /**
     * Add comment scripts.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/
     */
    public static function script()
    {
        if (is_singular() && comments_open()) {
            wp_enqueue_script('comment-reply');
        }
    }

    /**
     * Filter comment author link.
     *
     * @link https://developer.wordpress.org/reference/hooks/get_comment_author_link/
     */
    public static function authorLink(string $link): string
    {
        return str_replace("class='url'", 'class="uk-link-reset"', $link);
    }

    /**
     * Filter comment reply link.
     *
     * @link https://developer.wordpress.org/reference/hooks/comment_reply_link/
     */
    public static function replyLink(string $link): string
    {
        return str_replace(
            'comment-reply-link',
            'comment-reply-link uk-button uk-button-text',
            $link,
        );
    }

    /**
     * Filter comment cancel reply link.
     *
     * @link https://developer.wordpress.org/reference/hooks/cancel_comment_reply_link/
     */
    public static function cancelReplyLink(string $link): string
    {
        return str_replace('href="', 'class="uk-link-muted" href="', $link);
    }
}
