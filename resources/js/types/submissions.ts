export interface SubmissionMetadata {
    title: string;
    event_date: string;
    source_url: string;
    source_note: string;
    description: string;
    show_credit: boolean;
    album_id: number | null;
    category_ids: number[];
    tag_ids: number[];
}
export interface SubmissionDictionaries {
    categories: {
        id: number;
        name: string;
        children: { id: number; name: string }[];
    }[];
    albums: { id: number; title: string }[];
    tags: { id: number; name: string }[];
}
export const emptySubmissionMetadata = (): SubmissionMetadata => ({
    title: '',
    event_date: '',
    source_url: '',
    source_note: '',
    description: '',
    show_credit: false,
    album_id: null,
    category_ids: [],
    tag_ids: [],
});
