export async function shareRequest<T>(url: string, data?: unknown): Promise<T> {
    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));
    const response = await fetch(url, {
        method: data === undefined ? 'GET' : 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.slice(11)) : '',
        },
        body: data === undefined ? undefined : JSON.stringify(data),
    });
    if (!response.ok) {
        if (response.status === 419)
            throw new Error('页面已过期，请刷新后重试');
        if (response.status === 429) throw new Error('操作较频繁，请稍后重试');
        if (response.status === 403)
            throw new Error('当前账号或访问没有操作权限');
        if (response.status === 404 || response.status === 410)
            throw new Error('内容已隐藏或分享链接已停用');
        throw new Error('暂时无法完成操作，请稍后重试');
    }
    return response.json() as Promise<T>;
}
