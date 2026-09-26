{{-- Dark band that sits behind text placed on the light field, so contrast travels with the text.
     Alpha rises with depth (the field gets paler further down) and fades out in a fixed 64px tail; keep
     the last line of text above that tail. Spans the full field width (cancels the column padding) and
     runs up under the nav to the field's top edge. Requires the parent to be `relative` inside the
     layout's `isolate` column. --}}
<div aria-hidden="true" {{ $attributes->merge(['class' => 'pointer-events-none absolute -z-10 -inset-x-5 -top-32 bottom-0 sm:-inset-x-8']) }} style="background:linear-gradient(180deg, rgba(31,75,179,.55) 0%, rgba(31,75,179,.75) calc(100% - 64px), rgba(31,75,179,.5) calc(100% - 40px), rgba(31,75,179,.2) calc(100% - 16px), rgba(31,75,179,0) 100%)"></div>
