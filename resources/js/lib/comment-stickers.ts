import catalog from '../../data/douyin-stickers.json';
export const stickers = catalog;
export const stickerById = new Map(stickers.map((item) => [item.id, item]));
export function commentParts(text: string) {
    const parts: { text?: string; url?: string; name?: string }[] = [];
    const regex = /\[dy:([a-f0-9]{16})\]/g;
    let offset = 0;
    for (const match of text.matchAll(regex)) {
        const item = stickerById.get(match[1]);
        if (!item) continue;
        if (match.index! > offset)
            parts.push({ text: text.slice(offset, match.index) });
        parts.push({ url: item.url, name: item.name });
        offset = match.index! + match[0].length;
    }
    if (offset < text.length) parts.push({ text: text.slice(offset) });
    return parts;
}
