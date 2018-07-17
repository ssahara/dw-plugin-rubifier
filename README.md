# DokuWiki Rubifier plugin

Provide a ruby annotation of Aozora-Bunko style markup, which is used
to indicate the pronunciation or meaning of the corresponding characters.
This kind of annotation is often used in Japanese publications.
````
HTML:                  <ruby><rb>base<rt>annotation</ruby>
Rubifier(Aozora-Bunko syntax): ｜base《annotation》 
````
DokuWiki Rubifier plugin will provide simplified markup for ruby annotations.
The mark '`｜`' (U+FF5C; Fullwidth vertical line) is used to indicate explicitly the start position of the *base text* component. The *ruby text* component which must be enclosed between `《` and `》` (U+300A, U+300B; Double angle bracket) follows just after the relevant base text. The start mark `｜` can be omitted when the range of the base text is apparent or obvious.
