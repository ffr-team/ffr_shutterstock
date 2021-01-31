# ffr_shutterstock
PHP-скрипт для автоматизации загрузки изображений, собранных в коллекции на сайте shutterstock.

Файлы:
 - defines.php – здесь задаются необходимые входные данные (аккаунт, токен, идентификатор коллекции);
 - lib.php – библиотека функций;
 - load_collection.php – скрипт загрузки изображений;

## Проблемы загрузки изображений
При лицензировании (images/licenses) постоянно получается ошибка `This asset is outside of your collection, contact api@shutterstock.com for more information`.
В тех.поддержку shutterstock написано письмо, но ответа до сих пор нет.

На сайте есть раздел [про устранение неполадок](https://support.shutterstock.com/s/article/Troubleshooting-Downloading-and-Internet-Connection-Issues?language=ru).