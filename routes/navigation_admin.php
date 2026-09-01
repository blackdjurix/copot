<?php

use Copot\Core\NavigationRepository;
use Copot\Core\NavigationService;
use Copot\Core\PrimaryNavigationAdmin;
use Copot\Core\Response;

$navigationRepository = new NavigationRepository($app->database());
$navigationService = new NavigationService($app->database(), $navigationRepository);
$primaryNavigation = new PrimaryNavigationAdmin($app->database(), $navigationRepository, $navigationService, new \Copot\Core\ContentRepository($app->database()));
$navigationPath = $app->adminUrl()->childUrl('navigation');
$itemPath = static function (string $suffix = '') use ($app): string { return $app->adminUrl()->childUrl('navigation/items' . $suffix); };

$requireNavigation = static function ($request) use ($app): mixed {
    if (!$app->auth()->check()) return Response::redirect($app->adminUrl()->baseUrl());
    $user = $app->auth()->user();
    if (!$user?->can('admin.access') || !$user->can('navigation.manage')) return $app->adminErrors()->response($request, 403);
    return $user;
};
$render = static function (string $view, array $data, $user, string $path, int $status = 200) use ($app): Response {
    return Response::html($app->adminPageRenderer()->render(
        $view === 'items' ? 'Primary Navigation' : 'Navigation Item',
        $app->view()->render('admin/navigation/' . $view, $data),
        $user,
        $app->csrf()->token(),
        $path,
        ['description' => $view === 'items' ? 'Manage the Primary Navigation structure.' : 'Add or edit a Primary Navigation item.', 'bar' => null, 'surface' => 'transparent', 'spacing' => 'default'],
        $view === 'items' ? [] : [['label' => 'Navigation', 'url' => $app->adminUrl()->childUrl('navigation')], ['label' => 'Item']]
    ), $status);
};
$id = static fn (mixed $value): ?int => is_scalar($value) && preg_match('/^[1-9][0-9]*$/D', (string) $value) === 1 ? (int) $value : null;
$parents = static function (array $items, ?int $current = null): array {
    $byParent = [];
    foreach ($items as $item) $byParent[$item->parentId() ?? 0][] = $item;
    $excluded = [];
    if ($current !== null) {
        $stack = [$current];
        while ($stack) { $currentId = array_pop($stack); if (isset($excluded[$currentId])) continue; $excluded[$currentId] = true; foreach ($byParent[$currentId] ?? [] as $child) $stack[] = $child->id(); }
    }
    $result = [];
    $walk = function (int $parent, int $depth) use (&$walk, &$result, $byParent, $excluded): void {
        foreach ($byParent[$parent] ?? [] as $item) { if (isset($excluded[$item->id()])) continue; $result[] = ['item' => $item, 'depth' => $depth]; $walk($item->id(), $depth + 1); }
    };
    $walk(0, 0);
    return $result;
};
$data = static function ($request, int $menuId): array {
    $read = static fn (string $field): string => is_scalar($request->post($field, '')) ? trim((string) $request->post($field, '')) : '';
    $mode = $read('target_mode');
    $kind = $mode === 'custom' ? 'custom' : strtolower($read('target_kind'));
    return ['menu_id' => $menuId, 'parent_id' => ($read('parent_id') === '' ? null : (int) $read('parent_id')), 'label' => $read('label'), 'target_kind' => $kind, 'target_reference' => $kind === 'custom' ? null : $read('target_reference'), 'custom_url' => $kind === 'custom' ? $read('custom_url') : null, 'is_visible' => $request->post('is_visible') === '1'];
};
$app->adminNavigation()->add('Navigation', $navigationPath, ['navigation.manage'], 'navigation', 60);

$app->router()->get($navigationPath, function ($request) use ($app, $requireNavigation, $primaryNavigation, $navigationPath, $render): Response {
    $user = $requireNavigation($request); if ($user instanceof Response) return $user;
    try { $menu = $primaryNavigation->ensureMenu(); $items = (new NavigationService($app->database(), new NavigationRepository($app->database())))->itemsForMenu($menu->id()); $groups=[]; foreach($items as $entry) $groups[$entry->parentId() ?? 0][]=$entry->id(); $reorderActions=[]; foreach($groups as $parent=>$siblings) foreach($siblings as $index=>$siblingId){ if($index>0){$order=$siblings;[$order[$index-1],$order[$index]]=[$order[$index],$order[$index-1]];$reorderActions[$siblingId]['up']=['parent_id'=>$parent===0?'':$parent,'item_ids'=>$order];} if($index<count($siblings)-1){$order=$siblings;[$order[$index],$order[$index+1]]=[$order[$index+1],$order[$index]];$reorderActions[$siblingId]['down']=['parent_id'=>$parent===0?'':$parent,'item_ids'=>$order];}} }
    catch (Throwable) { return $app->adminErrors()->response($request, 503); }
    return $render('items', ['menu' => $menu, 'items' => $items, 'reorderActions' => $reorderActions, 'csrfToken' => $app->csrf()->token(), 'adminUrl' => fn (string $suffix = '') => $app->adminUrl()->childUrl('navigation' . $suffix), 'itemPath' => fn (string $suffix = '') => $app->adminUrl()->childUrl('navigation/items' . $suffix)], $user, $request->path());
});

$app->router()->get($itemPath('/create'), function ($request) use ($requireNavigation, $primaryNavigation, $render, $app, $parents): Response {
    $user = $requireNavigation($request); if ($user instanceof Response) return $user;
    try { $menu = $primaryNavigation->ensureMenu(); $items = (new NavigationService($app->database(), new NavigationRepository($app->database())))->itemsForMenu($menu->id()); }
    catch (Throwable) { return $app->adminErrors()->response($request, 503); }
    return $render('item-form', ['menu' => $menu, 'item' => ['label'=>'','parent_id'=>null,'target_mode'=>'custom','target_kind'=>'','target_reference'=>'','custom_url'=>'','is_visible'=>true], 'parents'=>$parents($items), 'formAction'=>$app->adminUrl()->childUrl('navigation/items'), 'errors'=>[], 'csrfToken'=>$app->csrf()->token()], $user, $request->path());
});
$app->router()->post($itemPath('/reorder'), function ($request) use ($app, $requireNavigation, $primaryNavigation, $navigationService): Response {
    $user = $requireNavigation($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    $parent = $request->post('parent_id', ''); $ids = $request->post('item_ids', null);
    if (($parent !== '' && (!is_scalar($parent) || !preg_match('/^[1-9][0-9]*$/D', (string) $parent))) || !is_array($ids)) return $app->adminErrors()->response($request, 422);
    try { $menu = $primaryNavigation->ensureMenu(); $navigationService->reorderSiblings($menu->id(), $parent === '' ? null : (int) $parent, array_map('intval', $ids)); return Response::redirect($app->adminUrl()->childUrl('navigation')); }
    catch (InvalidArgumentException) { return $app->adminErrors()->response($request, 422); } catch (Throwable) { return $app->adminErrors()->response($request, 503); }
});
$saveItem = static function ($request, ?array $params = null) use ($app, $requireNavigation, $primaryNavigation, $navigationService, $data, $render, $parents): Response {
    $user = $requireNavigation($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    try { $menu = $primaryNavigation->ensureMenu(); $payload = $data($request, $menu->id()); $itemId = $params['item'] ?? null; if ($itemId !== null) $primaryNavigation->updateItem((int) $itemId, $payload); else $primaryNavigation->createItem($payload); return Response::redirect($app->adminUrl()->childUrl('navigation')); }
    catch (InvalidArgumentException $exception) { $menu = $primaryNavigation->ensureMenu(); $items = $navigationService->itemsForMenu($menu->id()); $payload = $data($request, $menu->id()); $payload['target_mode'] = $payload['target_kind'] === 'custom' ? 'custom' : 'provider'; return $render('item-form', ['menu'=>$menu,'item'=>$payload,'parents'=>$parents($items),'formAction'=>$app->adminUrl()->childUrl('navigation/items' . ($params ? '/' . $params['item'] : '')),'errors'=>['form'=>[$exception->getMessage()]], 'csrfToken'=>$app->csrf()->token()], $user, $request->path(), 422); }
    catch (Throwable) { return $app->adminErrors()->response($request, 503); }
};
$app->router()->post($itemPath(''), fn ($request) => $saveItem($request));
$app->router()->get($itemPath('/{item}/edit'), function ($request, array $params) use ($app, $requireNavigation, $primaryNavigation, $navigationService, $render, $parents, $id): Response { $user=$requireNavigation($request); if($user instanceof Response)return $user; $itemId=$id($params['item']??null); try{$menu=$primaryNavigation->ensureMenu();$item=$itemId===null?null:$navigationService->findItem($itemId);if(!$item||$item->menuId()!==$menu->id())return $app->adminErrors()->response($request,404);$items=$navigationService->itemsForMenu($menu->id());return $render('item-form',['menu'=>$menu,'item'=>['label'=>$item->label(),'parent_id'=>$item->parentId(),'target_mode'=>$item->targetKind()==='custom'?'custom':'provider','target_kind'=>$item->targetKind()==='custom'?'':$item->targetKind(),'target_reference'=>$item->targetReference()??'','custom_url'=>$item->customUrl()??'','is_visible'=>$item->isVisible()],'parents'=>$parents($items,$item->id()),'formAction'=>$app->adminUrl()->childUrl('navigation/items/'.$item->id()),'errors'=>[]],$user,$request->path());}catch(Throwable){return $app->adminErrors()->response($request,503);} });
$app->router()->post($itemPath('/{item}/delete'), function ($request, array $params) use ($app, $requireNavigation, $navigationService, $id): Response { $user=$requireNavigation($request); if($user instanceof Response)return $user; if($app->csrf()->validateOrReject($request) instanceof Response)return $app->adminErrors()->response($request,419);$itemId=$id($params['item']??null);if($itemId===null)return $app->adminErrors()->response($request,404);try{$navigationService->deleteItem($itemId);return Response::redirect($app->adminUrl()->childUrl('navigation'));}catch(InvalidArgumentException){return $app->adminErrors()->response($request,404);}catch(Throwable){return $app->adminErrors()->response($request,503);} });
$app->router()->post($itemPath('/{item}'), function ($request, array $params) use ($saveItem, $id): Response { $itemId=$id($params['item']??null); return $itemId===null?Response::content('404 Not Found',404):$saveItem($request,['item'=>$itemId]); });
