export interface ThreadComment {
    id: number;
    user_name: string;
    content: string;
    created_at: string | null;
    liked: boolean;
    like_count: number;
    reply_to: string | null;
    reply_count?: number;
    replies?: ThreadComment[];
    author: {
        name: string;
        avatar: string | null;
        url: string | null;
        identities: string[];
        badges: {
            id: number;
            name: string;
            icon_key: string;
            color: string;
            image_url: string | null;
        }[];
    };
}
