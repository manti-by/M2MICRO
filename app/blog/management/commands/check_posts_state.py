import os
import logging

from django.core.management.base import BaseCommand

from blog.models import Post

logger = logging.getLogger('app')


class Command(BaseCommand):
    help = 'Check posts state'

    def handle(self, *args, **options):
        checked = 0
        for post in Post.objects.all():
            try:
                post.ogg_release_ready = os.path.exists(post.release_ogg_file) \
                    and os.path.getsize(post.release_ogg_file) > 0

                post.mp3_preview_ready = os.path.exists(post.preview_mp3_file) \
                    and os.path.getsize(post.preview_mp3_file) > 0

                post.ogg_preview_ready = os.path.exists(post.preview_ogg_file) \
                    and os.path.getsize(post.preview_ogg_file) > 0
                post.save()
                checked += 1
            except Exception as e:
                logger.error(e)

        self.stdout.write('Checked %d posts' % checked)
