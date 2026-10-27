package ru.sail.salesoutlets.domain.validation;

import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

import org.junit.jupiter.api.Test;

class RussianInnTest {

    @Test
    void acceptsTenOrTwelveDigits() {
        assertTrue(RussianInn.isValid("1234567890"));
        assertTrue(RussianInn.isValid("123456789012"));
    }

    @Test
    void rejectsInvalidFormats() {
        assertFalse(RussianInn.isValid(null));
        assertFalse(RussianInn.isValid(""));
        assertFalse(RussianInn.isValid("123"));
        assertFalse(RussianInn.isValid("12345678901"));
        assertFalse(RussianInn.isValid("abcdefghij"));
    }
}
